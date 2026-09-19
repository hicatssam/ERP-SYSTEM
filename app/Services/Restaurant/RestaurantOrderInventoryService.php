<?php

namespace App\Services\Restaurant;

use App\Enums\MovementReason;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderInventoryConsumption;
use App\Models\Product;
use App\Models\RestaurantMenuItem;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RestaurantOrderInventoryService
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    public function consumeForConfirmation(Order $order, User $user): void
    {
        $order->loadMissing(['items.product','items.productVariant','items.modifiers.modifier.ingredientAdjustments']);
        if (OrderInventoryConsumption::query()->where('order_id',$order->id)->exists()) return;
        $plan=$this->buildPlan($order);
        $this->assertAvailable((int)$order->location_id,collect($plan));
        foreach($plan as $row){
            $inventory=Inventory::query()->where('location_id',$order->location_id)->where('product_id',$row['stock_product_id'])->first();
            $unitCost=(float)($inventory?->unit_cost??0);
            $consumption=OrderInventoryConsumption::query()->create([
                'order_id'=>$order->id,'order_item_id'=>$row['order_item_id'],'location_id'=>$order->location_id,
                'sold_product_id'=>$row['sold_product_id'],'stock_product_id'=>$row['stock_product_id'],'recipe_id'=>$row['recipe_id'],
                'recipe_item_id'=>$row['recipe_item_id'],'source'=>$row['source'],'inventory_mode'=>$row['inventory_mode'],
                'order_quantity'=>$row['order_quantity'],'recipe_yield_quantity'=>$row['recipe_yield_quantity'],
                'quantity_per_yield'=>$row['quantity_per_yield'],'waste_percent'=>$row['waste_percent'],
                'consumed_quantity'=>$row['consumed_quantity'],'unit_cost_snapshot'=>$unitCost,
                'total_cost_snapshot'=>round($unitCost*$row['consumed_quantity'],4),'revision'=>0,
            ]);
            $this->inventoryService->decrease(locationId:(int)$order->location_id,productId:(int)$row['stock_product_id'],quantity:(float)$row['consumed_quantity'],reason:MovementReason::OrderSale,userId:(int)$user->id,referenceType:'orders',referenceId:(int)$order->id,idempotencyKey:"order:{$order->id}:consumption:{$consumption->id}:confirm",note:$this->movementNote($consumption,'sale'),unitCost:number_format($unitCost,4,'.',''));
        }
    }

    public function syncConfirmedQuantities(Order $order,array $newQuantityByOrderItemId,User $user): void
    {
        if($newQuantityByOrderItemId===[])return;
        $consumptions=OrderInventoryConsumption::query()->where('order_id',$order->id)->whereNull('restored_at')->orderBy('id')->get();
        if($consumptions->isEmpty()){ $this->syncLegacyDirectQuantities($order,$newQuantityByOrderItemId,$user); return; }
        $rows=[];
        foreach($consumptions as $consumption){
            $id=(int)$consumption->order_item_id;if(!array_key_exists($id,$newQuantityByOrderItemId))continue;
            $old=(float)$consumption->order_quantity;$new=round((float)$newQuantityByOrderItemId[$id],3);
            if($new<=0)throw ValidationException::withMessages(['items'=>'كمية صنف الطلب يجب أن تكون أكبر من صفر.']);
            if($old<=0)throw ValidationException::withMessages(['items'=>'تعذر احتساب استهلاك المخزون لسطر طلب قديم.']);
            $oldConsumed=(float)$consumption->consumed_quantity;$newConsumed=$this->stockQuantity(($oldConsumed/$old)*$new);$delta=$this->stockQuantity($newConsumed-$oldConsumed);
            $rows[]=['model'=>$consumption,'new_order_quantity'=>$new,'new_consumed_quantity'=>$newConsumed,'delta'=>$delta];
        }
        $positive=collect($rows)->filter(fn($r)=>$r['delta']>0)->map(fn($r)=>['stock_product_id'=>(int)$r['model']->stock_product_id,'consumed_quantity'=>(float)$r['delta']]);
        $this->assertAvailable((int)$order->location_id,$positive);
        foreach($rows as $row){$c=$row['model'];$delta=(float)$row['delta'];if($delta==0.0){if((float)$c->order_quantity!==(float)$row['new_order_quantity'])$c->update(['order_quantity'=>$row['new_order_quantity']]);continue;}$rev=((int)$c->revision)+1;$dir=$delta>0?'out':'in';if($delta>0){$this->inventoryService->decrease(locationId:(int)$order->location_id,productId:(int)$c->stock_product_id,quantity:$delta,reason:MovementReason::OrderSale,userId:(int)$user->id,referenceType:'orders',referenceId:(int)$order->id,idempotencyKey:"order:{$order->id}:consumption:{$c->id}:revision:{$rev}:{$dir}",note:$this->movementNote($c,'edit+'));}else{$this->inventoryService->increase(locationId:(int)$order->location_id,productId:(int)$c->stock_product_id,quantity:abs($delta),reason:MovementReason::OrderCancellation,userId:(int)$user->id,referenceType:'orders',referenceId:(int)$order->id,unitCost:$c->unit_cost_snapshot!==null?(string)$c->unit_cost_snapshot:null,idempotencyKey:"order:{$order->id}:consumption:{$c->id}:revision:{$rev}:{$dir}",note:$this->movementNote($c,'edit-'));}$unitCost=(float)($c->unit_cost_snapshot??0);$c->update(['order_quantity'=>$row['new_order_quantity'],'consumed_quantity'=>$row['new_consumed_quantity'],'total_cost_snapshot'=>round($unitCost*$row['new_consumed_quantity'],4),'revision'=>$rev]);}
    }

    public function restoreForCancellation(Order $order,User $user): void
    {
        $consumptions=OrderInventoryConsumption::query()->where('order_id',$order->id)->whereNull('restored_at')->orderBy('id')->get();
        if($consumptions->isEmpty()){$order->loadMissing('items');foreach($order->items as $item){$this->inventoryService->increase(locationId:(int)$order->location_id,productId:(int)$item->product_id,quantity:(float)$item->quantity,reason:MovementReason::OrderCancellation,userId:(int)$user->id,referenceType:'orders',referenceId:(int)$order->id,idempotencyKey:"order:{$order->id}:legacy-item:{$item->id}:cancel",note:'إلغاء طلب قديم قبل تفعيل سجل استهلاك الوصفات.');}return;}
        foreach($consumptions as $c){$this->inventoryService->increase(locationId:(int)$order->location_id,productId:(int)$c->stock_product_id,quantity:(float)$c->consumed_quantity,reason:MovementReason::OrderCancellation,userId:(int)$user->id,referenceType:'orders',referenceId:(int)$order->id,unitCost:$c->unit_cost_snapshot!==null?(string)$c->unit_cost_snapshot:null,idempotencyKey:"order:{$order->id}:consumption:{$c->id}:cancel",note:$this->movementNote($c,'cancel'));$c->update(['restored_at'=>now()]);}
    }

    private function buildPlan(Order $order): array
    {
        $plan=[];$isRestaurant=$order->isRestaurantOrder();$order->loadMissing(['items.product','items.productVariant','items.modifiers.modifier.ingredientAdjustments']);
        foreach($order->items as $orderItem){$product=$orderItem->product;if(!$product)throw ValidationException::withMessages(['items'=>"المنتج المرتبط بسطر الطلب #{$orderItem->id} غير موجود."]);$orderQty=$this->stockQuantity((float)$orderItem->quantity);if($orderQty<=0)throw ValidationException::withMessages(['items'=>'كل كميات الطلب يجب أن تكون أكبر من صفر.']);$menuItem=null;$inventoryMode='product';if($isRestaurant){$menuItem=RestaurantMenuItem::query()->where('location_id',$order->location_id)->where('product_id',$product->id)->first();$inventoryMode=$menuItem?->inventory_mode?:'auto';}$recipe=null;if($isRestaurant&&$inventoryMode!=='product')$recipe=$this->resolveActiveRecipe($product->id,$orderItem->product_variant_id);$useRecipe=$isRestaurant&&$inventoryMode!=='product'&&$recipe&&$recipe->items->isNotEmpty();if($isRestaurant&&$inventoryMode==='recipe'&&!$useRecipe)throw ValidationException::withMessages(['stock'=>['صنف المنيو "'.($menuItem?->displayName()?:$product->name_ar?:$product->name).'" مضبوط على خصم الوصفة، لكن لا توجد وصفة فعالة تحتوي مكونات للحجم المحدد.']]);$itemPlan=[];
            if(!$useRecipe){$itemPlan[(int)$product->id]=['order_item_id'=>(int)$orderItem->id,'sold_product_id'=>(int)$product->id,'stock_product_id'=>(int)$product->id,'recipe_id'=>null,'recipe_item_id'=>null,'source'=>'product','inventory_mode'=>$inventoryMode,'order_quantity'=>$orderQty,'recipe_yield_quantity'=>null,'quantity_per_yield'=>null,'waste_percent'=>0,'consumed_quantity'=>$orderQty];}
            else{$yield=(float)$recipe->yield_quantity;if($yield<=0)throw ValidationException::withMessages(['stock'=>['الوصفة الفعالة للمنتج "'.($product->name_ar?:$product->name).'" لديها كمية إنتاج Yield غير صالحة.']]);$factor=$orderQty/$yield;foreach($recipe->items as $ri){$consumed=$this->stockQuantity($ri->effectiveQuantity($factor));if($consumed<=0)continue;$pid=(int)$ri->ingredient_product_id;$itemPlan[$pid]=['order_item_id'=>(int)$orderItem->id,'sold_product_id'=>(int)$product->id,'stock_product_id'=>$pid,'recipe_id'=>(int)$recipe->id,'recipe_item_id'=>(int)$ri->id,'source'=>'recipe','inventory_mode'=>$inventoryMode,'order_quantity'=>$orderQty,'recipe_yield_quantity'=>$yield,'quantity_per_yield'=>(float)$ri->quantity,'waste_percent'=>(float)$ri->waste_percent,'consumed_quantity'=>$consumed];}}
            if($isRestaurant){foreach($orderItem->modifiers as $sm){$modifier=$sm->modifier;if(!$modifier)continue;$mq=max(1,(int)$sm->quantity);foreach($modifier->ingredientAdjustments as $adj){$pid=(int)$adj->ingredient_product_id;$delta=$this->stockQuantity($adj->stockQuantityDelta()*$orderQty*$mq);if($delta==0.0)continue;if(!isset($itemPlan[$pid])){if($delta<0)throw ValidationException::withMessages(['items'=>'إعداد الإضافة "'.$sm->modifier_name_snapshot.'" يحاول تقليل مكوّن غير موجود في الوصفة الأساسية.']);$itemPlan[$pid]=['order_item_id'=>(int)$orderItem->id,'sold_product_id'=>(int)$product->id,'stock_product_id'=>$pid,'recipe_id'=>$recipe?->id,'recipe_item_id'=>null,'source'=>'modifier','inventory_mode'=>$inventoryMode,'order_quantity'=>$orderQty,'recipe_yield_quantity'=>$recipe?->yield_quantity,'quantity_per_yield'=>null,'waste_percent'=>0,'consumed_quantity'=>$delta];continue;}$next=$this->stockQuantity((float)$itemPlan[$pid]['consumed_quantity']+$delta);if($next<0)throw ValidationException::withMessages(['items'=>'إعداد الإضافة "'.$sm->modifier_name_snapshot.'" يخفض استهلاك أحد المكونات إلى قيمة سالبة.']);$itemPlan[$pid]['consumed_quantity']=$next;$itemPlan[$pid]['source']=$itemPlan[$pid]['source']==='recipe'?'recipe_modifier':$itemPlan[$pid]['source'];}}}
            $itemRows=collect($itemPlan)->filter(fn($r)=>(float)$r['consumed_quantity']>0)->values();if($itemRows->isEmpty())throw ValidationException::withMessages(['stock'=>['سطر الطلب "'.($orderItem->product_name?:$product->name_ar?:$product->name).'" لا ينتج أي استهلاك مخزني صالح.']]);foreach($itemRows as $row)$plan[]=$row;
        }return $plan;
    }

    private function resolveActiveRecipe(int $productId,?int $variantId): ?Recipe
    {
        return Recipe::query()->with('items')->where('product_id',$productId)->where('is_active',true)->when($variantId,fn($q)=>$q->where(function($qq)use($variantId){$qq->where('product_variant_id',$variantId)->orWhereNull('product_variant_id');}))->orderByRaw('product_variant_id IS NULL')->first();
    }

    private function assertAvailable(int $locationId,Collection $rows): void
    {
        $requiredByProduct=$rows->groupBy('stock_product_id')->map(fn(Collection $g)=>$this->stockQuantity($g->sum('consumed_quantity')));$stockErrors=[];
        foreach($requiredByProduct->sortKeys() as $productId=>$required){Inventory::query()->firstOrCreate(['location_id'=>$locationId,'product_id'=>(int)$productId],['quantity'=>0,'reserved_quantity'=>0,'damaged_quantity'=>0,'in_transit_quantity'=>0,'unit_cost'=>0]);$inventory=Inventory::query()->where('location_id',$locationId)->where('product_id',(int)$productId)->lockForUpdate()->firstOrFail();$available=max(0,round((float)$inventory->quantity-(float)$inventory->reserved_quantity,3));if($available+0.0005>=$required)continue;$p=Product::query()->find((int)$productId);$name=$p?->name_ar??$p?->name??"Product #{$productId}";$stockErrors[]='المخزون غير كافٍ للمكوّن/المنتج "'.$name.'". المطلوب: '.number_format($required,3).'، المتاح: '.number_format($available,3).'، العجز: '.number_format(max(0,$required-$available),3).'.';}
        // Keep inventory failures under one dedicated validation key so the
        // order page can reliably open its stock-shortage dialog after redirect.
        if($stockErrors!==[])throw ValidationException::withMessages(['stock'=>$stockErrors]);
    }

    private function syncLegacyDirectQuantities(Order $order,array $newQuantityByOrderItemId,User $user): void
    {
        $order->loadMissing('items.product');$positiveRows=[];$deltas=[];foreach($order->items as $item){if(!array_key_exists((int)$item->id,$newQuantityByOrderItemId))continue;$new=$this->stockQuantity((float)$newQuantityByOrderItemId[(int)$item->id]);if($new<=0)throw ValidationException::withMessages(['items'=>'كمية صنف الطلب يجب أن تكون أكبر من صفر.']);$delta=$this->stockQuantity($new-(float)$item->quantity);$deltas[]=['item'=>$item,'delta'=>$delta];if($delta>0)$positiveRows[]=['stock_product_id'=>(int)$item->product_id,'consumed_quantity'=>$delta];}$this->assertAvailable((int)$order->location_id,collect($positiveRows));foreach($deltas as $row){$item=$row['item'];$delta=(float)$row['delta'];if($delta>0)$this->inventoryService->decrease(locationId:(int)$order->location_id,productId:(int)$item->product_id,quantity:$delta,reason:MovementReason::OrderSale,userId:(int)$user->id,referenceType:'orders',referenceId:(int)$order->id);elseif($delta<0)$this->inventoryService->increase(locationId:(int)$order->location_id,productId:(int)$item->product_id,quantity:abs($delta),reason:MovementReason::OrderCancellation,userId:(int)$user->id,referenceType:'orders',referenceId:(int)$order->id);}
    }

    private function stockQuantity(float $q): float{return round($q,3);}
    private function movementNote(OrderInventoryConsumption $c,string $action): string{return "{$action} order inventory consumption #{$c->id}";}
}

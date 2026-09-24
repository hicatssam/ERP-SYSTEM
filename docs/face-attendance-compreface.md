# Face Attendance — CompreFace

This project uses a self-hosted CompreFace Face Recognition service for
employee face attendance. No paid face-recognition gateway is required.

## Local Windows setup

Laravel currently uses port 8000, so expose CompreFace on port 8001.

1. Install Docker Desktop.
2. Start CompreFace:

```powershell
docker run -d --name CompreFace `
  -v compreface-db:/var/lib/postgresql/data `
  -p 127.0.0.1:8001:80 `
  exadel/compreface
```

3. Wait for startup:

```powershell
docker logs CompreFace -f
```

4. Open:

```text
http://127.0.0.1:8001/login
```

5. Create an application called `Dahab Attendance`.
6. Create a **Face Recognition** service inside that application.
7. Copy that service's API key.

## Laravel environment

Add to `.env`:

```env
ATTENDANCE_FACE_PROVIDER=compreface
COMPREFACE_BASE_URL=http://127.0.0.1:8001
COMPREFACE_API_KEY=YOUR_FACE_RECOGNITION_SERVICE_KEY
COMPREFACE_TIMEOUT_SECONDS=12
COMPREFACE_DET_PROB_THRESHOLD=0.80
COMPREFACE_SIMILARITY_THRESHOLD=0.78
COMPREFACE_FRONT_MAX_ABS_YAW=15
COMPREFACE_TURNED_MIN_ABS_YAW=18
COMPREFACE_MIN_YAW_DELTA=14
FACE_ATTENDANCE_CHALLENGE_TTL_SECONDS=90
FACE_ATTENDANCE_MIN_CHECKOUT_GAP_SECONDS=60
```

Then run:

```powershell
php artisan optimize:clear
```

Use **Settings → Attendance & Payroll → CompreFace → Test connection**.

## Enrollment flow

Employee face enrollment captures three browser-camera images:

1. Front.
2. Slight turn right.
3. Slight turn left.

Laravel forwards the image data server-to-server to CompreFace and never
exposes the CompreFace API key to JavaScript.

Laravel stores only the employee/provider mapping, an encrypted CompreFace
subject identifier, enrollment metadata, and audit information.

## Attendance flow

The kiosk uses two frames:

1. Front-facing frame.
2. Head-turned frame.

CompreFace recognizes the employee in both frames and returns the
`pose.yaw` value. Laravel only creates a punch when:

- both frames recognize the same CompreFace subject;
- both similarities exceed the configured threshold;
- the first frame is approximately forward-facing;
- the second frame has enough head rotation;
- the yaw difference meets the configured liveness threshold;
- the employee belongs to the kiosk branch.

The head-pose check is a **basic active liveness control**, not certified PAD.

## Camera security

Browser camera access is available on secure contexts. For testing on the
same PC, localhost can be used. For a phone/tablet connecting over the LAN,
serve the Laravel kiosk over HTTPS.

## Removing a face

Revoking a face profile deletes the CompreFace subject and its stored
examples before marking the ERP profile as revoked.

## Useful Docker commands

```powershell
docker ps
docker stop CompreFace
docker start CompreFace
docker logs CompreFace -f
docker rm -f CompreFace
```

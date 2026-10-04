# Face Attendance — CompreFace

This project uses a self-hosted CompreFace Face Recognition service for
employee face attendance. No paid face-recognition gateway is required.

## Local Windows setup

Laravel currently uses port 8000, so expose CompreFace on port 8001.

1. Install and launch Docker Desktop, then wait for the Linux engine to start. Verify with `docker info`.
2. Download the **release archive** from [CompreFace releases](https://github.com/exadel-inc/CompreFace/releases) and extract it to a permanent directory outside the ERP project. CompreFace needs its full Compose stack (UI, API, core, admin, PostgreSQL); a single `docker run exadel/compreface` is not a working installation.
3. Open PowerShell in the extracted directory containing `docker-compose.yml` and `.env`. Change the UI port mapping in `docker-compose.yml` from `"8000:80"` to `"127.0.0.1:8001:80"` so it does not conflict with Laravel and is reachable only on this computer.
4. Start the complete stack:

```powershell
docker compose up -d
docker compose ps
```

5. Wait for startup (the first launch downloads several images and may take time):

```powershell
docker compose logs -f --tail 50
```

6. Open:

```text
http://127.0.0.1:8001/login
```

7. Create an application called `Dahab Attendance`.
8. Create a **Face Recognition** service inside that application. Its model must provide the **head pose** plugin used by the attendance check.
9. Copy that service's API key.

In the ERP, open **Settings → Attendance & Payroll → CompreFace** and paste
the key into **Face Recognition API key**. Save, then click **Test connection**.
The field stays empty on later visits; an empty submission retains the saved
key. The database value is encrypted with Laravel's `APP_KEY`. Keep that key
backed up, or saved service credentials cannot be decrypted after a key change.
The environment variable below is still supported for deployments that manage
secrets outside the application. A key saved in Settings takes precedence.

## Laravel environment

The URL and API key can be saved in ERP Settings instead of `.env`. These optional `.env` values are useful for server-managed configuration:

```env
ATTENDANCE_FACE_PROVIDER=compreface
COMPREFACE_BASE_URL=http://127.0.0.1:8001
COMPREFACE_API_KEY=
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
docker compose ps
docker compose stop
docker compose up -d
docker compose logs --tail 50
docker compose down
```

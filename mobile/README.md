# HalalScan Android debug build

The debug APK is a Capacitor shell that opens a single Laravel backend origin. The phone and Mac must be on the same Wi-Fi network.

## Start the local backend

From the repository root:

```bash
cd backend
php artisan serve --host=0.0.0.0 --port=8001
```

Check the address from the Mac before building:

```bash
default_interface="$(route -n get default | awk '/interface:/{print $2}')"
lan_ip="$(ipconfig getifaddr "$default_interface")"
curl --fail "http://${lan_ip}:8001/up"
```

If the IP address changes, rebuild the APK with the new origin. Port `8001` is used here because another local service may already own port `8000`.

## Build the APK

Capacitor 8 and the barcode scanner require Node.js 22, Android SDK 36, and Java 21.

```bash
cd mobile
default_interface="$(route -n get default | awk '/interface:/{print $2}')"
lan_ip="$(ipconfig getifaddr "$default_interface")"
export HALALSCAN_SERVER_URL="http://${lan_ip}:8001"
export HALALSCAN_BUILD_PROFILE=debug
export JAVA_HOME="$(/usr/libexec/java_home -v 21)"
npm run android:sync
cd android
./gradlew testDebugUnitTest lintDebug assembleDebug
```

The resulting file is `mobile/android/app/build/outputs/apk/debug/app-debug.apk`.

## Install on a connected phone

Enable Android developer options and USB debugging, authorize this Mac, then run:

```bash
adb devices
adb install -r mobile/android/app/build/outputs/apk/debug/app-debug.apk
```

If the app shows its retry page, confirm that the backend process is still running, both devices are on the same Wi-Fi, and the phone can open `HALALSCAN_SERVER_URL` in its browser. Then tap **Повторить**.

Local HTTP and cleartext traffic are enabled only in the debug Android manifest. The staging profile rejects HTTP and must be built against an HTTPS backend.

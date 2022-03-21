# MJPG-ReStreamer
This PHP script connects to a MJPG/MJPEG camera stream, extracts just the pictures and retransmits them as a MJPG-stream

In contrast to common HTTP(s) reverse-proxies this script just passes the MJPEG stream and prevents accessing the upstream camera details and settings. This protects the potentially vulnerable MJPEG-IP-Camera from exploiting it through the internet.

## Usage
```
<img src="index.php"/>
```

## Caching, multiple connections, saving bandwidth to upstream camera
MJPG-ReStreamer uses a shared memory segment to limit the bandwidth to the upstream MJPEG-camera. If multiple clients connect they get served the images from the shared memory. If the first instance stops working one of the other instances will connect to the upstream camera and update all other instances.

# MJPG-ReStreamer
This PHP script connects to a MJPG/MJPEG camera stream, extracts just the pictures and retransmits them as a MJPG-stream

In contrast to common HTTP(s) reverse-proxies this script just passes the MJPEG stream and prevents accessing the upstream camera details and settings. This protects the potentially vulnerable MJPEG-IP-Camera from exploiting it through the internet.

## Usage
```
<img src="index.php"/>
```

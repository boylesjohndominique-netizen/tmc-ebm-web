import { useEffect, useRef, useState } from 'react';

export default function ReceiptQrScanner({ onScan, message = 'Scan only after checking the physical student ID and inspecting all listed equipment. A valid scan confirms the handover using those checks.' }) {
  const videoRef = useRef(null);
  const onScanRef = useRef(onScan);
  const [error, setError] = useState('');
  onScanRef.current = onScan;

  useEffect(() => {
    let cancelled = false;
    let handled = false;
    let controls;
    const video = videoRef.current;

    const start = async () => {
      try {
        if (!navigator.mediaDevices?.getUserMedia) {
          throw new Error('Camera access requires HTTPS or localhost. You can search the receipt number instead.');
        }
        const { BrowserQRCodeReader } = await import('@zxing/browser');
        if (cancelled) return;
        const reader = new BrowserQRCodeReader();
        controls = await reader.decodeFromConstraints(
          { audio: false, video: { facingMode: { ideal: 'environment' } } },
          video,
          (result, _error, scannerControls) => {
            if (!result || handled || cancelled) return;
            handled = true;
            scannerControls.stop();
            onScanRef.current(result.getText());
          },
        );
        if (cancelled) controls.stop();
      } catch (err) {
        if (!cancelled) setError(err.message || 'Unable to open the camera. Allow camera permission or search by receipt number.');
      }
    };
    start();
    return () => {
      cancelled = true;
      controls?.stop();
      video?.srcObject?.getTracks().forEach(track => track.stop());
    };
  }, []);

  return <div className="modal-body">
    <p>{message}</p>
    {error ? <div className="alert error" role="alert">{error}</div> :
      <video ref={videoRef} className="receipt-camera" autoPlay muted playsInline aria-label="Receipt QR scanner camera" />}
  </div>;
}

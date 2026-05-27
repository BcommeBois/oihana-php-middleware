<?php

namespace oihana\middleware\enums ;

use oihana\reflect\traits\ConstantsTrait ;

/**
 * Feature names recognised by the `Permissions-Policy` response header.
 *
 * Lists the policy-controlled features that browsers currently honour
 * in 2026, grouped by category for readability. The vocabulary is
 * **open** — {@see \oihana\middleware\helpers\security\buildPermissionsPolicyHeader()}
 * accepts raw feature names too, so callers can target features not
 * yet exposed by this enum without waiting for an update.
 *
 * Each constant value is the canonical feature token used in the header
 * value (lowercase, kebab-case). The enum stays opinion-free on which
 * features SHOULD be disabled — that depends on the application — but
 * a typical "deny everything sensitive" baseline targets `CAMERA`,
 * `MICROPHONE`, `GEOLOCATION`, `PAYMENT`, `USB`, `MIDI`, `BLUETOOTH`,
 * `HID`, `SERIAL`, `IDLE_DETECTION` and `LOCAL_FONTS`.
 *
 * @see https://www.w3.org/TR/permissions-policy/
 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Permissions-Policy
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\enums
 */
class PermissionsPolicyFeature
{
    use ConstantsTrait ;

    // -------------------------------------------------------------------------
    // Privacy-sensitive — typically denied by default.
    // -------------------------------------------------------------------------

    /**
     * `camera` — access to the device camera via `getUserMedia()`.
     */
    public const string CAMERA = 'camera' ;

    /**
     * `microphone` — access to the device microphone via `getUserMedia()`.
     */
    public const string MICROPHONE = 'microphone' ;

    /**
     * `geolocation` — access to the user's location via the Geolocation API.
     */
    public const string GEOLOCATION = 'geolocation' ;

    /**
     * `payment` — access to the Payment Request API.
     */
    public const string PAYMENT = 'payment' ;

    /**
     * `usb` — access to connected USB devices via the WebUSB API.
     */
    public const string USB = 'usb' ;

    /**
     * `midi` — access to MIDI devices via the Web MIDI API.
     */
    public const string MIDI = 'midi' ;

    /**
     * `bluetooth` — access to Bluetooth devices via the Web Bluetooth API.
     */
    public const string BLUETOOTH = 'bluetooth' ;

    /**
     * `hid` — access to Human Interface Devices via the WebHID API.
     */
    public const string HID = 'hid' ;

    /**
     * `serial` — access to serial-port devices via the Web Serial API.
     */
    public const string SERIAL = 'serial' ;

    // -------------------------------------------------------------------------
    // Embedding & media playback.
    // -------------------------------------------------------------------------

    /**
     * `fullscreen` — access to the Fullscreen API.
     */
    public const string FULLSCREEN = 'fullscreen' ;

    /**
     * `picture-in-picture` — access to the Picture-in-Picture API.
     */
    public const string PICTURE_IN_PICTURE = 'picture-in-picture' ;

    /**
     * `autoplay` — automatic playback of media without user interaction.
     */
    public const string AUTOPLAY = 'autoplay' ;

    /**
     * `encrypted-media` — access to the Encrypted Media Extensions (EME) for DRM-protected content.
     */
    public const string ENCRYPTED_MEDIA = 'encrypted-media' ;

    /**
     * `display-capture` — screen-capture via `getDisplayMedia()`.
     */
    public const string DISPLAY_CAPTURE = 'display-capture' ;

    /**
     * `speaker-selection` — selection of audio output devices via `setSinkId()`.
     */
    public const string SPEAKER_SELECTION = 'speaker-selection' ;

    // -------------------------------------------------------------------------
    // Sensors.
    // -------------------------------------------------------------------------

    /**
     * `accelerometer` — access to the accelerometer sensor.
     */
    public const string ACCELEROMETER = 'accelerometer' ;

    /**
     * `gyroscope` — access to the gyroscope sensor.
     */
    public const string GYROSCOPE = 'gyroscope' ;

    /**
     * `magnetometer` — access to the magnetometer sensor.
     */
    public const string MAGNETOMETER = 'magnetometer' ;

    /**
     * `ambient-light-sensor` — access to ambient-light readings.
     */
    public const string AMBIENT_LIGHT_SENSOR = 'ambient-light-sensor' ;

    /**
     * `compute-pressure` — system-load observation via the Compute Pressure API.
     */
    public const string COMPUTE_PRESSURE = 'compute-pressure' ;

    /**
     * `gamepad` — access to connected gamepads via the Gamepad API.
     */
    public const string GAMEPAD = 'gamepad' ;

    // -------------------------------------------------------------------------
    // Identity, authentication, storage.
    // -------------------------------------------------------------------------

    /**
     * `publickey-credentials-get` — read WebAuthn credentials via `navigator.credentials.get()`.
     */
    public const string PUBLICKEY_CREDENTIALS_GET = 'publickey-credentials-get' ;

    /**
     * `publickey-credentials-create` — create WebAuthn credentials via `navigator.credentials.create()`.
     */
    public const string PUBLICKEY_CREDENTIALS_CREATE = 'publickey-credentials-create' ;

    /**
     * `identity-credentials-get` — Federated Credential Management (FedCM).
     */
    public const string IDENTITY_CREDENTIALS_GET = 'identity-credentials-get' ;

    /**
     * `otp-credentials` — Web OTP API (SMS-delivered one-time passwords).
     */
    public const string OTP_CREDENTIALS = 'otp-credentials' ;

    /**
     * `storage-access` — Storage Access API (cross-site cookie access).
     */
    public const string STORAGE_ACCESS = 'storage-access' ;

    // -------------------------------------------------------------------------
    // Clipboard, sharing, fonts, screen.
    // -------------------------------------------------------------------------

    /**
     * `clipboard-read` — programmatic clipboard reads via `navigator.clipboard.read()`.
     */
    public const string CLIPBOARD_READ = 'clipboard-read' ;

    /**
     * `clipboard-write` — programmatic clipboard writes via `navigator.clipboard.write()`.
     */
    public const string CLIPBOARD_WRITE = 'clipboard-write' ;

    /**
     * `web-share` — Web Share API (`navigator.share()`).
     */
    public const string WEB_SHARE = 'web-share' ;

    /**
     * `screen-wake-lock` — Screen Wake Lock API.
     */
    public const string SCREEN_WAKE_LOCK = 'screen-wake-lock' ;

    /**
     * `idle-detection` — Idle Detection API.
     */
    public const string IDLE_DETECTION = 'idle-detection' ;

    /**
     * `local-fonts` — Local Font Access API (enumerate installed system fonts).
     */
    public const string LOCAL_FONTS = 'local-fonts' ;

    /**
     * `window-management` — Window Management API (multi-screen placement; supersedes `window-placement`).
     */
    public const string WINDOW_MANAGEMENT = 'window-management' ;

    // -------------------------------------------------------------------------
    // Tracking, attribution, AR/VR.
    // -------------------------------------------------------------------------

    /**
     * `attribution-reporting` — Attribution Reporting API.
     */
    public const string ATTRIBUTION_REPORTING = 'attribution-reporting' ;

    /**
     * `browsing-topics` — Topics API (interest-based advertising signal).
     */
    public const string BROWSING_TOPICS = 'browsing-topics' ;

    /**
     * `xr-spatial-tracking` — WebXR spatial tracking.
     */
    public const string XR_SPATIAL_TRACKING = 'xr-spatial-tracking' ;

    /**
     * `cross-origin-isolated` — controls whether descendants can opt into cross-origin isolation.
     */
    public const string CROSS_ORIGIN_ISOLATED = 'cross-origin-isolated' ;

    /**
     * `battery` — access to the Battery Status API.
     */
    public const string BATTERY = 'battery' ;

    /**
     * `keyboard-map` — Keyboard Map API (`navigator.keyboard.getLayoutMap()`).
     */
    public const string KEYBOARD_MAP = 'keyboard-map' ;

    // -------------------------------------------------------------------------
    // Deprecated — still parsed by browsers, kept for documentation.
    // -------------------------------------------------------------------------

    /**
     * `interest-cohort` — FLoC API. **Deprecated** and replaced by `BROWSING_TOPICS`.
     */
    public const string INTEREST_COHORT = 'interest-cohort' ;

    /**
     * `document-domain` — control over `document.domain` setter. **Deprecated** — `document.domain` is being removed.
     */
    public const string DOCUMENT_DOMAIN = 'document-domain' ;

    /**
     * `sync-xhr` — synchronous `XMLHttpRequest`. **Deprecated** — synchronous XHR is removed from main threads.
     */
    public const string SYNC_XHR = 'sync-xhr' ;

    /**
     * `execution-while-not-rendered` — keeps script execution running while the iframe is not rendered.
     */
    public const string EXECUTION_WHILE_NOT_RENDERED = 'execution-while-not-rendered' ;

    /**
     * `execution-while-out-of-viewport` — keeps script execution running while the iframe is outside the viewport.
     */
    public const string EXECUTION_WHILE_OUT_OF_VIEWPORT = 'execution-while-out-of-viewport' ;
}

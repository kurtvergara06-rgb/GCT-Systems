# FROMS Desktop: Windows installer distribution

This guide is for the Windows Tauri desktop application, not a browser PWA shortcut.

## Before enabling the website download

1. Build and test the separate `froms-desktop` Tauri project on Windows.
2. Confirm email/password login, Google authentication, navigation, downloads, printing, and logout.
3. Build a release installer with `npm run tauri build` in the desktop project. Tauri Windows bundles are usually under `src-tauri/target/release/bundle/`.
4. Upload the tested `.exe` or `.msi` file to a stable HTTPS destination such as a GitHub Release asset. Prefer a signed Windows installer for company distribution.
5. Set `FROMS_DESKTOP_INSTALLER_URL` in the Laravel Render web service to the **direct HTTPS asset URL**, ending in `.exe` or `.msi` (optional query string is supported), and redeploy or refresh cached configuration.

Do not point the website to the Tauri source repository, the locally running development executable, a generic Releases page, or a dummy link.

## Website behavior

- The login page offers **Download for Windows** only when the configured URL is a valid HTTPS `.exe`/`.msi` URL.
- The dialog explains that a native Windows installer (not the PWA shortcut) will be downloaded.
- Clicking **Download installer** records an intent-to-download flag in that browser's localStorage and hides the main download button. A **Download again** link remains available for failed or incomplete downloads.
- This is not a Windows installation detector: website JavaScript cannot reliably determine whether the download completed or whether the app was installed. Clearing browser data or using another browser/device resets that flag.
- If the URL is not configured, no misleading installer control is displayed.
- The old PWA manifest, service worker, offline fallback, and install prompts have been removed. The shared desktop runtime unregisters the previous service worker for returning browsers; existing PWA shortcuts already installed on employee devices must be removed manually.

## Security and maintenance

The Tauri app should open only the configured HTTPS GCT web origin, avoid exposing privileged native IPC to remote content, and use a normal installer/update release process. Keep the Laravel/Python services and MySQL database unchanged.

## Hide the download button inside the installed Tauri application

The Laravel login page detects the cosmetic startup query parameter `?froms_desktop=1` and stores it in the webview's sessionStorage. This hides both Windows installer buttons and the download modal before the login page paints, while regular browsers continue to show the normal download action. This flag is **not** an authentication or authorization boundary.

In the separate, locally stored Tauri project, modify only the URL used to create the website child webview in `src-tauri/src/lib.rs`:
- Local development: `http://gct_system.test?froms_desktop=1`
- Render release: `https://gct-systems.onrender.com?froms_desktop=1`

Do not add the parameter to the local titlebar webview, and do not grant native IPC permissions to the website webview. Rebuild the installer after changing the website URLs, then test logout/reload behavior. The website-only change cannot alter previously built Windows executables.

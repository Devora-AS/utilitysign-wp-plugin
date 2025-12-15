# FTP Upload Instructions - WordPress Plugin Bundle Update

## Files to Upload

The following files need to be uploaded via FTP to update the WordPress plugin with the latest fixes:

### 1. Frontend JavaScript Bundle (CRITICAL)
- **Source**: `wp-plugin/utilitysign/assets/frontend/dist/assets/main-6a2c892d.js`
- **Destination**: `/wp-content/plugins/utilitysign/assets/frontend/dist/assets/main-6a2c892d.js`
- **Size**: ~272 KB (gzipped: ~86 KB)
- **Purpose**: Contains promise handling fixes, enhanced error handling, and production-ready code

### 2. Manifest File (CRITICAL)
- **Source**: `wp-plugin/utilitysign/assets/frontend/dist/manifest.json`
- **Destination**: `/wp-content/plugins/utilitysign/assets/frontend/dist/manifest.json`
- **Purpose**: Tells WordPress which bundle file to load

### 3. CSS Bundle (Optional - only if CSS changed)
- **Source**: `wp-plugin/utilitysign/assets/frontend/dist/assets/main-df46c5d0.css`
- **Destination**: `/wp-content/plugins/utilitysign/assets/frontend/dist/assets/main-df46c5d0.css`
- **Note**: Filename hasn't changed, but upload if you want to ensure latest version

## Upload Steps

1. **Connect to FTP**:
   - Host: `pilot.eiker.devora.no` (or your staging server)
   - Navigate to: `/wp-content/plugins/utilitysign/assets/frontend/dist/assets/`

2. **Upload Files**:
   - Upload `main-6a2c892d.js` (new bundle)
   - Upload `manifest.json` (updated manifest)
   - Optionally upload `main-df46c5d0.css` if CSS changed

3. **Verify Upload**:
   - Check file permissions (should be 644 or 755)
   - Verify file sizes match local files
   - Clear WordPress cache if using caching plugin

4. **Test**:
   - Visit: `https://pilot.eiker.devora.no/utilitysign/?utilitysign_debug=1`
   - Open browser console (F12)
   - Check that `main-6a2c892d.js` is loaded (not older versions)
   - Submit the form and check console logs for enhanced error messages
   - Verify no unhandled promise rejections

## What's Fixed in This Bundle

1. **Enhanced CORS Error Logging**: Detailed error messages to help diagnose CORS issues
2. **Improved Form Submission**: Better handling of form data and validation
3. **Debug Mode**: Enhanced logging when `?utilitysign_debug=1` is in URL
4. **Error Handling**: Better error messages for network and CORS errors

## Troubleshooting

If the old bundle (`main-32cec80a.js`) is still being loaded after upload:

1. **Clear Browser Cache**: Hard refresh (Ctrl+Shift+R / Cmd+Shift+R)
2. **Clear WordPress Cache**: If using caching plugin, clear all caches
3. **Check File Permissions**: Ensure files are readable (644 or 755)
4. **Verify Manifest**: Check that `manifest.json` references `main-6a2c892d.js`
5. **Check WordPress Debug**: Enable `WP_DEBUG` to see if there are any PHP errors

## Current Status

- ✅ **Backend API**: CORS headers correctly configured and working (verified with curl)
- ✅ **Backend Journey**: Standalone form testing successful (email ✅, download ✅, document opens ✅)
- ✅ **CORS Configuration**: Production domain `https://eikerenergi.no` added to allowed origins
- ✅ **Frontend Bundle**: Built successfully (`main-6a2c892d.js`)
- ⏳ **Deployment**: Waiting for FTP/SSH upload of new bundle to staging server
- ⏳ **Testing**: Will test after bundle upload

## Next Steps After Upload

1. Test form submission with `?utilitysign_debug=1` parameter
2. Check browser console for enhanced error messages
3. Verify CORS headers are present in network tab
4. Test end-to-end signing flow


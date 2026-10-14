# DUDHO organized assets

These files were copied from your uploaded ZIP and renamed by purpose. No artwork was regenerated or cropped.

- `illustrations/` and `states/`: screen artwork. Use native React Native text and buttons outside images.
- `branding/`: logo assets, including candidates that require review.
- `app/`: launcher icon candidate; not yet Android adaptive production exports.
- `splash/`: existing splash reference is landscape; create final portrait/native splash from approved logo.
- `references/`: unused concepts and combined sheets; do not import these into the app.
- `asset-manifest.json`: exact file mapping, dimensions, review status, and missing files.

## React Native example

```tsx
<Image source={require('./assets/illustrations/welcome.png')} resizeMode="contain" />
```

Copy only approved assets into your app's assets directory. Review candidate files before production. PNG alpha channels exist but some image artwork may include intentional background elements.

## Missing production assets
- `branding/logo-white.png`
- `branding/logo-monochrome.png`
- `app/android-adaptive-foreground.png`
- `app/notification-icon.png`
- `app/favicon.png`
- `splash/splash-logo.png`
- `states/no-collections.png`

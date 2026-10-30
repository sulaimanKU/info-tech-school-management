# TreadExellence startup logo

No TreadExellence logo asset existed in the project when this startup was added.
The current startup uses a typography-only **TE** placeholder, with the exact
developer/company name **TreadExellence**. The school keeps its own identity.

Place an approved transparent logo at `mobile/assets/branding/treadexellence.png`.
Add that file to the `flutter.assets` list in `mobile/pubspec.yaml`, then replace
the TE child in `lib/shared/widgets/branded_startup.dart` with `Image.asset`.
Supply appropriate resolution variants if required. Do not replace the existing
school logo assets with the company logo.

The immediate Android launch mark is a lightweight vector placeholder at
`mobile/android/app/src/main/res/drawable/treadexellence_mark.xml`; replace its
vector artwork with approved launch-safe branding when available. Its inset
artwork is designed for the Android 12 splash icon safe region. Pre-Android 12
launch backgrounds use the same mark. No downloaded/generated logo is bundled.

The Flutter entrance lasts at most 1.8 seconds, runs only during actual session
and preference initialization, and does not delay navigation after those finish.
The progress indicator may continue when initialization genuinely takes longer.
Reduced-motion settings disable the entrance and moving progress indicator.

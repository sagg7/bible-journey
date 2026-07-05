# iOS handoff — Bible Journey subscriptions (RevenueCat)

Written 2026-07-05 by Claude Code running on Saúl's Windows machine, for whoever
(Claude or Saúl) picks this up on a Mac.

> **⚠️ DELETE THIS FILE once the iOS setup below is done (or once you've
> read/used it and no longer need it).** It's intentionally committed to this
> **public** repo so it travels via `git pull` without a manual transfer step,
> but it doesn't need to stick around afterward. It does not contain live
> secrets (no API keys, passwords, or webhook secrets are pasted in — just
> product/price IDs and file paths), but there's no reason to leave a
> repo/account map like this sitting in a public repo longer than it's useful.
> `git rm IOS_HANDOFF.md && git commit` when you're done with it.

## 0. CRITICAL — do this before anything else

The Windows machine has **3 local commits that failed to push** (recurring
`wincredman` credential-store error in that Claude Code session — not a code
problem, just a broken git-credential helper there). As of this writing,
`origin/main` on GitHub does **not** have the entire subscriptions feature
(RevenueCat webhook, Stripe/Cashier institutional billing, CRS content
gating, paywall screen, verse highlighting). The unpushed commits are:

```
ab845c6 Support monthly/annual institutional pricing and enforce 10-seat minimum
5458b06 Merge remote changes (verse highlighting, harmonization cycle-break fix) with subscriptions work
f4a03c9 Add subscriptions: individual (RevenueCat) + institutional (Stripe/Cashier)
```

**Before starting iOS work, confirm with Saúl that these are pushed** (he can
push from Windows once the credential issue is sorted, or you can pull the
repo some other way). If `git log origin/main` doesn't show `ab845c6` as the
tip of `main`, stop and sort that out first — otherwise you'll be building
against stale code.

Repo: `https://github.com/sagg7/bible-journey.git`, branch `main`.

## 1. Do we already have paywalls? — Yes, mostly

The Flutter paywall UI is done and is cross-platform (same Dart code serves
iOS and Android):

- `mobile/lib/screens/paywall_screen.dart` — lists RevenueCat offerings,
  handles purchase flow, copy references the free "Los patriarcas" /
  "David y Salomón" narrative arc.
- `mobile/lib/core/api.dart` — `configureRevenueCat()`, `Purchases.logIn()`
  wired into the `meProvider` (Riverpod), reads keys via
  `--dart-define=REVENUECAT_API_KEY_ANDROID=...` /
  `--dart-define=REVENUECAT_API_KEY_IOS=...` (both currently blank —
  `String.fromEnvironment` defaults to `''`, and `configureRevenueCat()`
  swallows the resulting error so the app just runs with purchases silently
  disabled if the key is missing).
- `mobile/lib/screens/crs_reader_screen.dart`, `read_screen.dart` — already
  render locked/premium content state (grayed out, lock icon, "Suscribirse"
  CTA) based on the `locked` flag the backend API returns per node.
- `mobile/lib/core/router.dart` — `/suscripcion` route already registered.
- `mobile/android/app/src/main/AndroidManifest.xml` — has the
  `com.android.vending.BILLING` permission (Android-only, irrelevant to iOS).
- `mobile/pubspec.yaml` — `purchases_flutter: ^10.4.0` already added.

**What's NOT done — this is the actual iOS gap:**
1. No `In-App Purchase` capability enabled in the Xcode project (no
   entitlements file exists yet — `find ios -iname "*.entitlements"` returns
   nothing as of this writing).
2. No iOS app connected in RevenueCat (only Android is connected — see §2).
3. No subscription products created in App Store Connect.
4. No `REVENUECAT_API_KEY_IOS` value.
5. iOS code signing (Apple Developer certificates / provisioning profiles)
   has never been set up — this is a completely separate system from the
   Android upload keystore and can only be done from a Mac with Xcode and an
   Apple Developer Program membership.

So: the mobile *code* needs zero changes for iOS. This is purely App Store
Connect + RevenueCat dashboard configuration + Xcode signing/capability
setup, then a build.

## 2. Current state of the backend + other platforms (context, don't redo)

- **Backend** (Laravel 13 + Filament 5, `backend/`): subscriptions system
  fully implemented — `User::hasPremiumAccess()`, RevenueCat webhook
  (`POST /webhooks/revenuecat`), Stripe/Cashier institutional billing,
  `is_premium` gating on `ChronologicalReadingSet` nodes. Verified end-to-end
  locally. **Not yet deployed to production** (Site5) as of this writing —
  ask Saúl before deploying; see `HANDOFF.md` (also gitignored, also needs
  manual transfer if you need it) for deploy steps/gotchas.
- **Stripe (institutional billing)**: fully configured in **test mode**.
  Product `prod_UpZwhv5ScbBaAn`, monthly price `price_1TpuqSCuPt8gMkOPxTQUPybj`
  ($4.95/seat/mo), annual price `price_1TpuqTCuPt8gMkOPDF3hIFJt`
  ($49.50/seat/yr, 2 months free), webhook registered at
  `https://biblejourney-api.codeshore.net/stripe/webhook`. Values live in
  `backend/.env` on the Windows machine (gitignored, not on GitHub either —
  ask Saúl if you need to replicate this locally on the Mac for testing).
  Not relevant to iOS work directly, but same pricing philosophy applies to
  the individual plan below.
- **Individual pricing decided by Saúl**: $6.99/month (no annual tier
  discussed yet for individual — ask if one should exist before creating App
  Store Connect products).
- **RevenueCat — Android side, done**:
  - Project "Bible Journey" created.
  - Android app connected: package `com.codeshore.biblejourney`, using the
    existing service account `C:\Users\garci\keystores\play-publisher-service-account.json`
    (also on Windows only — you'll need Saúl to generate a fresh one or
    transfer it if you need to touch RevenueCat's Android config from the
    Mac, which you shouldn't need to).
  - That service account was granted **"Ver datos financieros..."**
    permission in Play Console (`Usuarios y permisos` →
    `play-publisher@bible-journey-play-publish.iam.gserviceaccount.com` →
    pestaña "Permisos de la cuenta" → sección "Datos financieros").
  - **Not yet confirmed done**: whether an Entitlement (e.g. `premium`) and
    an Offering (e.g. `default`) exist yet in the RevenueCat project, and
    whether the Android subscription products
    (`individual_mensual`/`individual_anual`) were actually created in Google
    Play Console → Suscripciones. **Verify this with Saúl before assuming
    it's there** — Play Console's Suscripciones section was blocked behind
    "no production release yet" until today; a production release
    (versionCode 7 / 1.0.7) was just submitted for Google review as of
    2026-07-05, so the Android subscription product may still need to be
    created once that review clears.
- **Play Store**: app `com.codeshore.biblejourney`, developer account "Saúl
  García" (there are other unrelated developer accounts on the same Google
  login — Codeshore, EcoWave Resources LLC, QualiaVR, Quantumware INC — don't
  touch those). Production release (versionCode 7) submitted for review
  2026-07-05, ~7 days typical turnaround.

## 3. Step-by-step for iOS

### A. Prerequisites (ask Saúl if unsure)
- Confirm he has an active **Apple Developer Program** membership ($99/yr) —
  required for App Store Connect, TestFlight, and code signing. Without this
  none of the below works.
- Confirm the 3 unpushed commits from §0 are actually on GitHub before
  cloning/pulling.

### B. App Store Connect
1. https://appstoreconnect.apple.com → My Apps → check if an app record for
   bundle ID `com.codeshore.biblejourney` already exists. If not, create one
   (name "Bible Journey", primary language Spanish, SKU can be
   `com.codeshore.biblejourney`).
2. Complete Agreements, Tax, and Banking (Business section) if not already
   done — App Store won't let you sell anything (including subscriptions)
   until this is filled in.
3. **In-App Purchase shared secret**: Users and Access → Integrations →
   In-App Purchase (or under the app's General → App Information, depending
   on current App Store Connect UI) → generate/copy the **App-Specific
   Shared Secret**. RevenueCat needs this.
4. **Create the subscription**: Features (or Monetization) → Subscriptions
   → create a Subscription Group (e.g. "Bible Journey Premium") → add a
   subscription product, e.g. `individual_mensual`, $6.99/month, matching
   the same price point as Android. Ask Saúl if he wants an annual tier
   mirroring the institutional 2-months-free pattern (institutional annual
   is $49.50/seat/yr vs $4.95×12=$59.40, i.e. ~17% off) before creating one —
   don't invent a price yourself.

### C. Xcode (on the Mac, after pulling the repo)
1. `cd mobile && flutter pub get`
2. Open `ios/Runner.xcworkspace` in Xcode (not `.xcodeproj`).
3. Select the Runner target → Signing & Capabilities:
   - Set your Team (Apple Developer account) for signing.
   - Click "+ Capability" → add **In-App Purchase**.
4. Verify bundle identifier still reads `com.codeshore.biblejourney` (it
   should already, per `project.pbxproj`).

### D. RevenueCat
1. https://app.revenuecat.com → same project "Bible Journey" already used
   for Android.
2. Apps → Add app → App Store.
   - Bundle ID: `com.codeshore.biblejourney`
   - Paste the App-Specific Shared Secret from step B.3.
3. Products → import/add the `individual_mensual` product created in step
   B.4 (and annual, if created).
4. Attach it to the same Entitlement used for Android (check if `premium`
   already exists — if not, create it) and the same Offering (`default`).
5. Project settings → API keys → copy the **Public app-specific API key**
   for the iOS app → this is `REVENUECAT_API_KEY_IOS`.
6. RevenueCat webhook is already configured pointing at
   `https://biblejourney-api.codeshore.net/api/webhooks/revenuecat` with a
   shared secret — this is platform-agnostic (RevenueCat unifies both
   stores into one webhook stream), no changes needed here for iOS.

### E. Build and test
1. Simulator/device smoke test:
   ```
   flutter run --dart-define=API_BASE_URL=https://biblejourney-api.codeshore.net/api --dart-define=REVENUECAT_API_KEY_IOS=<key from D.5>
   ```
2. Real purchase testing requires a **Sandbox Apple ID**: App Store Connect
   → Users and Access → Sandbox Testers → create one, then on a real iOS
   device (not simulator, for real StoreKit purchase flow) sign into
   Settings → App Store → Sandbox Account with that tester.
3. Release build for TestFlight:
   ```
   flutter build ipa --dart-define=API_BASE_URL=https://biblejourney-api.codeshore.net/api --dart-define=REVENUECAT_API_KEY_IOS=<key> --dart-define=REVENUECAT_API_KEY_ANDROID=<key, if you have it, harmless if omitted>
   ```
   Then upload via Xcode Organizer (Window → Organizer → Distribute App) or
   Transporter.

### F. Before wider release
- Confirm `is_premium` gating and the free narrative arc ("Los patriarcas",
  "David y Salomón") behave the same on iOS as observed on Android — should
  be automatic since it's all backend-driven, but worth a manual check.
- Ask Saúl before submitting the iOS app for App Store review — same "don't
  publish without checking" rule that applied to the Android production
  release.

## 4. Things to explicitly NOT do without asking Saúl first
- Don't create Apple Developer certificates/provisioning profiles changes
  that affect other apps under the same account.
- Don't touch the other Play Console developer accounts you might see if
  cross-checking anything (Codeshore, EcoWave Resources LLC, QualiaVR,
  Quantumware INC) — only "Saúl García" account has Bible Journey.
- Don't submit the iOS app for App Store review without explicit
  confirmation, same as the Android production submission required it.
- Don't invent subscription prices — ask if anything beyond the $6.99/month
  individual price isn't already decided.

import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_ar.dart';
import 'app_localizations_en.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'generated/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('ar'),
    Locale('en'),
  ];

  /// Brand name, used as the accessible label of the logo mark.
  ///
  /// In ar, this message translates to:
  /// **'بافو'**
  String get appName;

  /// Brand tagline (EN: Best and Final Offer). The Arabic line is a candidate pending client approval (05_brand.md §2.9).
  ///
  /// In ar, this message translates to:
  /// **'أفضل عرض نهائي'**
  String get appTagline;

  /// Bottom navigation: home tab.
  ///
  /// In ar, this message translates to:
  /// **'الرئيسية'**
  String get navHome;

  /// Bottom navigation: competitions the organisation takes part in (participant side).
  ///
  /// In ar, this message translates to:
  /// **'مشاركاتي'**
  String get navCompetitions;

  /// Bottom navigation: competitions the organisation issues (issuer side).
  ///
  /// In ar, this message translates to:
  /// **'منافساتي'**
  String get navMyCompetitions;

  /// Short label of the issuer-side tab in the bottom navigation (fits one line in a five-tab bar); the page title stays navMyCompetitions.
  ///
  /// In ar, this message translates to:
  /// **'منافساتي'**
  String get navMyCompetitionsTab;

  /// Bottom navigation: notifications tab.
  ///
  /// In ar, this message translates to:
  /// **'الإشعارات'**
  String get navNotifications;

  /// Bottom navigation: account tab.
  ///
  /// In ar, this message translates to:
  /// **'الحساب'**
  String get navAccount;

  /// Name of the Arabic language, always written in Arabic.
  ///
  /// In ar, this message translates to:
  /// **'العربية'**
  String get commonLanguageArabic;

  /// Name of the English language, always written in English.
  ///
  /// In ar, this message translates to:
  /// **'English'**
  String get commonLanguageEnglish;

  /// Accessible label of the pre-login language switch.
  ///
  /// In ar, this message translates to:
  /// **'تغيير اللغة'**
  String get commonLanguageSwitch;

  /// Login screen title.
  ///
  /// In ar, this message translates to:
  /// **'تسجيل الدخول'**
  String get authLoginTitle;

  /// Login screen subtitle.
  ///
  /// In ar, this message translates to:
  /// **'سجّل الدخول إلى حساب منشأتك في بافو.'**
  String get authLoginSubtitle;

  /// E-mail field label.
  ///
  /// In ar, this message translates to:
  /// **'البريد الإلكتروني'**
  String get authFieldsEmailLabel;

  /// E-mail field placeholder.
  ///
  /// In ar, this message translates to:
  /// **'name@company.sa'**
  String get authFieldsEmailHint;

  /// Password field label.
  ///
  /// In ar, this message translates to:
  /// **'كلمة المرور'**
  String get authFieldsPasswordLabel;

  /// Accessible label: reveal the password.
  ///
  /// In ar, this message translates to:
  /// **'إظهار كلمة المرور'**
  String get commonPasswordShow;

  /// Accessible label: hide the password.
  ///
  /// In ar, this message translates to:
  /// **'إخفاء كلمة المرور'**
  String get commonPasswordHide;

  /// Login submit button.
  ///
  /// In ar, this message translates to:
  /// **'تسجيل الدخول'**
  String get authLoginSubmit;

  /// Form validation: empty required field.
  ///
  /// In ar, this message translates to:
  /// **'هذا الحقل مطلوب.'**
  String get validationRequired;

  /// Form validation: malformed e-mail.
  ///
  /// In ar, this message translates to:
  /// **'أدخل بريداً إلكترونياً صحيحاً.'**
  String get validationEmail;

  /// Participant competitions empty state title.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد دعوات حتى الآن'**
  String get competitionsParticipatingEmptyTitle;

  /// Participant competitions empty state message.
  ///
  /// In ar, this message translates to:
  /// **'عندما يدعوك طارح منافسة للمشاركة ستظهر الدعوة هنا.'**
  String get competitionsParticipatingEmptyMessage;

  /// Notifications empty state title.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد إشعارات'**
  String get notificationsEmptyTitle;

  /// Notifications empty state message.
  ///
  /// In ar, this message translates to:
  /// **'ستظهر هنا تحديثات المنافسات والعروض.'**
  String get notificationsEmptyMessage;

  /// Account screen: language setting header.
  ///
  /// In ar, this message translates to:
  /// **'لغة التطبيق'**
  String get profileLanguageTitle;

  /// Account screen: sign-out button and dialog confirm action.
  ///
  /// In ar, this message translates to:
  /// **'تسجيل الخروج'**
  String get authLogoutAction;

  /// Sign-out confirmation dialog title.
  ///
  /// In ar, this message translates to:
  /// **'تسجيل الخروج؟'**
  String get authLogoutConfirmTitle;

  /// Sign-out confirmation dialog message.
  ///
  /// In ar, this message translates to:
  /// **'ستحتاج إلى تسجيل الدخول مرة أخرى للوصول إلى حسابك.'**
  String get authLogoutConfirmMessage;

  /// Generic retry action.
  ///
  /// In ar, this message translates to:
  /// **'إعادة المحاولة'**
  String get commonActionsRetry;

  /// Generic cancel action.
  ///
  /// In ar, this message translates to:
  /// **'إلغاء'**
  String get commonActionsCancel;

  /// Generic confirm action.
  ///
  /// In ar, this message translates to:
  /// **'تأكيد'**
  String get commonActionsConfirm;

  /// Generic close action (sheets, dialogs).
  ///
  /// In ar, this message translates to:
  /// **'إغلاق'**
  String get commonActionsClose;

  /// Accessible label for loading indicators and skeletons.
  ///
  /// In ar, this message translates to:
  /// **'جارٍ التحميل'**
  String get commonLoading;

  /// Error state title.
  ///
  /// In ar, this message translates to:
  /// **'تعذّر إكمال الطلب'**
  String get commonErrorTitle;

  /// Fallback error message when the server gives none.
  ///
  /// In ar, this message translates to:
  /// **'حدث خطأ غير متوقع. حاول مرة أخرى.'**
  String get errorsServerError;

  /// No connection / host unreachable.
  ///
  /// In ar, this message translates to:
  /// **'تعذّر الاتصال بالخادم. تحقّق من اتصالك بالإنترنت.'**
  String get errorsNetworkError;

  /// Request timed out.
  ///
  /// In ar, this message translates to:
  /// **'استغرق الطلب وقتاً أطول من المتوقع. حاول مرة أخرى.'**
  String get errorsTimeout;

  /// Shown after the API rejected the stored token.
  ///
  /// In ar, this message translates to:
  /// **'انتهت جلستك. سجّل الدخول مرة أخرى.'**
  String get errorsUnauthenticated;

  /// Blocking screen when the API answers 426 app_version_unsupported.
  ///
  /// In ar, this message translates to:
  /// **'يلزم تحديث التطبيق'**
  String get commonUpdateRequiredTitle;

  /// Update-required explanation.
  ///
  /// In ar, this message translates to:
  /// **'هذا الإصدار من بافو لم يعد مدعوماً. حدّث التطبيق من المتجر للمتابعة.'**
  String get commonUpdateRequiredMessage;

  /// Blocking screen when the API answers 503 maintenance.
  ///
  /// In ar, this message translates to:
  /// **'بافو تحت الصيانة'**
  String get commonMaintenanceTitle;

  /// Fallback maintenance text when the server sends none.
  ///
  /// In ar, this message translates to:
  /// **'نعمل على تحسين الخدمة. حاول مرة أخرى بعد قليل.'**
  String get commonMaintenanceMessage;

  /// Whole days left in a countdown, followed by HH:MM:SS.
  ///
  /// In ar, this message translates to:
  /// **'{count, plural, =1{يوم واحد} =2{يومان} few{{count} أيام} many{{count} يوماً} other{{count} يوم}}'**
  String commonCountdownDays(int count);

  /// Countdown reached zero.
  ///
  /// In ar, this message translates to:
  /// **'انتهى الوقت'**
  String get commonCountdownEnded;

  /// Accessible label of a countdown.
  ///
  /// In ar, this message translates to:
  /// **'الوقت المتبقي {time}'**
  String commonCountdownRemaining(String time);

  /// Accessible label of the notifications tab badge.
  ///
  /// In ar, this message translates to:
  /// **'{count, plural, =0{لا توجد إشعارات غير مقروءة} =1{إشعار واحد غير مقروء} =2{إشعاران غير مقروءين} few{{count} إشعارات غير مقروءة} many{{count} إشعاراً غير مقروء} other{{count} إشعار غير مقروء}}'**
  String navNotificationsUnread(int count);

  /// Generic next action (steppers, onboarding).
  ///
  /// In ar, this message translates to:
  /// **'التالي'**
  String get commonActionsNext;

  /// Generic back action (steppers).
  ///
  /// In ar, this message translates to:
  /// **'السابق'**
  String get commonActionsBack;

  /// Skip the onboarding slides.
  ///
  /// In ar, this message translates to:
  /// **'تخطي'**
  String get commonActionsSkip;

  /// Generic save action.
  ///
  /// In ar, this message translates to:
  /// **'حفظ'**
  String get commonActionsSave;

  /// Download a file.
  ///
  /// In ar, this message translates to:
  /// **'تنزيل'**
  String get commonActionsDownload;

  /// Open a file or link.
  ///
  /// In ar, this message translates to:
  /// **'فتح'**
  String get commonActionsOpen;

  /// Generic done action.
  ///
  /// In ar, this message translates to:
  /// **'تم'**
  String get commonActionsDone;

  /// Clear a search field.
  ///
  /// In ar, this message translates to:
  /// **'مسح'**
  String get commonActionsClear;

  /// Search field label.
  ///
  /// In ar, this message translates to:
  /// **'بحث'**
  String get commonActionsSearch;

  /// Open a picker.
  ///
  /// In ar, this message translates to:
  /// **'اختيار'**
  String get commonActionsSelect;

  /// Remove an item.
  ///
  /// In ar, this message translates to:
  /// **'إزالة'**
  String get commonActionsRemove;

  /// Image source sheet: camera.
  ///
  /// In ar, this message translates to:
  /// **'التقاط صورة'**
  String get commonActionsCamera;

  /// Image source sheet: photo library.
  ///
  /// In ar, this message translates to:
  /// **'اختيار من الصور'**
  String get commonActionsGallery;

  /// Suffix announced after the label of a required field.
  ///
  /// In ar, this message translates to:
  /// **'مطلوب'**
  String get commonFieldRequired;

  /// Suffix shown after the label of an optional field.
  ///
  /// In ar, this message translates to:
  /// **'اختياري'**
  String get commonFieldOptional;

  /// Stepper position.
  ///
  /// In ar, this message translates to:
  /// **'الخطوة {current} من {total}'**
  String commonStepOf(int current, int total);

  /// Accessible label of a page indicator.
  ///
  /// In ar, this message translates to:
  /// **'الصفحة {current} من {total}'**
  String commonPageOf(int current, int total);

  /// Disabled button label during a 429 cooldown.
  ///
  /// In ar, this message translates to:
  /// **'أعد المحاولة بعد {seconds} ث'**
  String commonRetryIn(int seconds);

  /// Shown under every price input and price list.
  ///
  /// In ar, this message translates to:
  /// **'الأسعار لا تشمل ضريبة القيمة المضافة'**
  String get commonPricesExcludeVat;

  /// Offline banner (M05).
  ///
  /// In ar, this message translates to:
  /// **'لا يوجد اتصال بالإنترنت.'**
  String get commonOfflineBanner;

  /// Shown when a mutation is attempted offline.
  ///
  /// In ar, this message translates to:
  /// **'لا يمكن تنفيذ هذا الإجراء دون اتصال بالإنترنت.'**
  String get commonOfflineActionsDisabled;

  /// A file is downloading.
  ///
  /// In ar, this message translates to:
  /// **'جارٍ التنزيل'**
  String get commonDownloading;

  /// Download error.
  ///
  /// In ar, this message translates to:
  /// **'تعذّر تنزيل الملف.'**
  String get commonDownloadFailed;

  /// Opening a downloaded file failed.
  ///
  /// In ar, this message translates to:
  /// **'لا يوجد تطبيق على جهازك يفتح هذا الملف.'**
  String get commonNoAppToOpen;

  /// Opening an external link failed.
  ///
  /// In ar, this message translates to:
  /// **'تعذّر فتح الرابط.'**
  String get commonLinkOpenFailed;

  /// Accessible label of an external link attachment.
  ///
  /// In ar, this message translates to:
  /// **'رابط خارجي'**
  String get commonExternalLink;

  /// Badge on a document added after publish.
  ///
  /// In ar, this message translates to:
  /// **'ملحق'**
  String get commonAddendum;

  /// File size in bytes.
  ///
  /// In ar, this message translates to:
  /// **'{size} بايت'**
  String commonFileSizeBytes(String size);

  /// File size in kilobytes.
  ///
  /// In ar, this message translates to:
  /// **'{size} كيلوبايت'**
  String commonFileSizeKb(String size);

  /// File size in megabytes.
  ///
  /// In ar, this message translates to:
  /// **'{size} ميجابايت'**
  String commonFileSizeMb(String size);

  /// A deadline in Riyadh time.
  ///
  /// In ar, this message translates to:
  /// **'{dateTime} بتوقيت الرياض'**
  String commonTimeRiyadh(String dateTime);

  /// Relative time under one minute.
  ///
  /// In ar, this message translates to:
  /// **'الآن'**
  String get commonTimeJustNow;

  /// Relative time in minutes.
  ///
  /// In ar, this message translates to:
  /// **'{count, plural, =1{منذ دقيقة} =2{منذ دقيقتين} few{منذ {count} دقائق} many{منذ {count} دقيقة} other{منذ {count} دقيقة}}'**
  String commonTimeMinutesAgo(int count);

  /// Relative time in hours.
  ///
  /// In ar, this message translates to:
  /// **'{count, plural, =1{منذ ساعة} =2{منذ ساعتين} few{منذ {count} ساعات} many{منذ {count} ساعة} other{منذ {count} ساعة}}'**
  String commonTimeHoursAgo(int count);

  /// Relative time in days (under 7).
  ///
  /// In ar, this message translates to:
  /// **'{count, plural, =1{منذ يوم} =2{منذ يومين} few{منذ {count} أيام} many{منذ {count} يوماً} other{منذ {count} يوم}}'**
  String commonTimeDaysAgo(int count);

  /// Forbidden state title (S8 F).
  ///
  /// In ar, this message translates to:
  /// **'لا تملك صلاحية الوصول'**
  String get commonForbiddenTitle;

  /// Not-found state title (S8 N).
  ///
  /// In ar, this message translates to:
  /// **'غير متاحة'**
  String get commonNotFoundTitle;

  /// Support contacts header on account gates.
  ///
  /// In ar, this message translates to:
  /// **'تواصل مع دعم بافو'**
  String get commonSupportTitle;

  /// Support contact: e-mail.
  ///
  /// In ar, this message translates to:
  /// **'البريد الإلكتروني'**
  String get commonSupportEmail;

  /// Support contact: phone.
  ///
  /// In ar, this message translates to:
  /// **'الهاتف'**
  String get commonSupportPhone;

  /// Support contact: WhatsApp.
  ///
  /// In ar, this message translates to:
  /// **'واتساب'**
  String get commonSupportWhatsapp;

  /// Opens the store listing on the update-required screen (not a purchase).
  ///
  /// In ar, this message translates to:
  /// **'تحديث التطبيق'**
  String get commonUpdateAction;

  /// Full-page account gate (account_inactive, organization_suspended).
  ///
  /// In ar, this message translates to:
  /// **'لا يمكن استخدام الحساب حالياً'**
  String get commonAccountBlockedTitle;

  /// Accessible state of a satisfied password rule.
  ///
  /// In ar, this message translates to:
  /// **'متحقق'**
  String get commonRuleMet;

  /// Accessible state of an unsatisfied password rule.
  ///
  /// In ar, this message translates to:
  /// **'غير متحقق'**
  String get commonRuleNotMet;

  /// Multi-select summary.
  ///
  /// In ar, this message translates to:
  /// **'{count, plural, =0{لم يُختر شيء} =1{عنصر واحد مختار} =2{عنصران مختاران} few{{count} عناصر مختارة} many{{count} عنصراً مختاراً} other{{count} عنصر مختار}}'**
  String commonSelectedCount(int count);

  /// Date-time field placeholder (Riyadh time).
  ///
  /// In ar, this message translates to:
  /// **'اختر التاريخ والوقت'**
  String get commonDateTimePick;

  /// Phone format (+9665XXXXXXXX).
  ///
  /// In ar, this message translates to:
  /// **'أدخل رقم جوال سعودي من 9 أرقام يبدأ بالرقم 5.'**
  String get validationPhone;

  /// CR format.
  ///
  /// In ar, this message translates to:
  /// **'رقم السجل التجاري 10 أرقام.'**
  String get validationCr;

  /// VAT format.
  ///
  /// In ar, this message translates to:
  /// **'الرقم الضريبي 15 رقماً يبدأ وينتهي بالرقم 3.'**
  String get validationVat;

  /// Password rule failure.
  ///
  /// In ar, this message translates to:
  /// **'كلمة المرور لا تستوفي الشروط.'**
  String get validationPasswordRules;

  /// Confirmation mismatch.
  ///
  /// In ar, this message translates to:
  /// **'كلمتا المرور غير متطابقتين.'**
  String get validationPasswordMismatch;

  /// Website must be https.
  ///
  /// In ar, this message translates to:
  /// **'أدخل رابطاً يبدأ بـ https://'**
  String get validationUrlHttps;

  /// Text too long.
  ///
  /// In ar, this message translates to:
  /// **'الحد الأقصى {max} حرفاً.'**
  String validationMaxLength(int max);

  /// A numeric field of a fixed length (4, 5 or 10).
  ///
  /// In ar, this message translates to:
  /// **'أدخل {count} أرقام.'**
  String validationExactDigits(int count);

  /// National short address format.
  ///
  /// In ar, this message translates to:
  /// **'العنوان المختصر 4 أحرف إنجليزية كبيرة ثم 4 أرقام، مثل ABCD1234.'**
  String get validationShortAddress;

  /// OTP format.
  ///
  /// In ar, this message translates to:
  /// **'أدخل الرمز المكوّن من 6 أرقام.'**
  String get validationOtp;

  /// Too many categories.
  ///
  /// In ar, this message translates to:
  /// **'يمكنك اختيار 20 فئة كحد أقصى.'**
  String get validationCategoriesMax;

  /// Money input format.
  ///
  /// In ar, this message translates to:
  /// **'أدخل مبلغاً صحيحاً بخانتين عشريتين كحد أقصى.'**
  String get validationAmount;

  /// Money input must be > 0.
  ///
  /// In ar, this message translates to:
  /// **'يجب أن يكون المبلغ أكبر من صفر.'**
  String get validationAmountPositive;

  /// Money input granularity 100.
  ///
  /// In ar, this message translates to:
  /// **'أدخل المبلغ بالريال دون هللات.'**
  String get validationAmountWholeRiyals;

  /// Welcome slide 1: language switch header.
  ///
  /// In ar, this message translates to:
  /// **'اختر لغة التطبيق'**
  String get authWelcomeLanguageTitle;

  /// Welcome slide 1 title.
  ///
  /// In ar, this message translates to:
  /// **'اطرح منافستك'**
  String get authWelcomeSlide1Title;

  /// Welcome slide 1 body.
  ///
  /// In ar, this message translates to:
  /// **'أنشئ مناقصة أو مزايدة بقواعد واضحة وجدول زمني محدد.'**
  String get authWelcomeSlide1Body;

  /// Welcome slide 2 title.
  ///
  /// In ar, this message translates to:
  /// **'ادعُ المتنافسين'**
  String get authWelcomeSlide2Title;

  /// Welcome slide 2 body.
  ///
  /// In ar, this message translates to:
  /// **'ادعُ الموردين أو المزايدين بالبريد الإلكتروني أو من الاقتراحات، وتابع انضمامهم.'**
  String get authWelcomeSlide2Body;

  /// Welcome slide 3 title.
  ///
  /// In ar, this message translates to:
  /// **'منافسة مباشرة وترسية'**
  String get authWelcomeSlide3Title;

  /// Welcome slide 3 body.
  ///
  /// In ar, this message translates to:
  /// **'تصل العروض مباشرة ويُعتمد وقتها على خادم بافو، ثم تتم الترسية بوضوح.'**
  String get authWelcomeSlide3Body;

  /// Welcome CTA to register.
  ///
  /// In ar, this message translates to:
  /// **'إنشاء حساب منشأة'**
  String get authWelcomeCreateAccount;

  /// Link to the forgot-password screen.
  ///
  /// In ar, this message translates to:
  /// **'نسيت كلمة المرور؟'**
  String get authLoginForgot;

  /// Prompt before the register link.
  ///
  /// In ar, this message translates to:
  /// **'ليس لدى منشأتك حساب؟'**
  String get authLoginNoAccount;

  /// Link to register.
  ///
  /// In ar, this message translates to:
  /// **'أنشئ حساباً'**
  String get authLoginCreateAccount;

  /// Register screen title.
  ///
  /// In ar, this message translates to:
  /// **'إنشاء حساب منشأة'**
  String get authRegisterTitle;

  /// Register step 1 title.
  ///
  /// In ar, this message translates to:
  /// **'بيانات الحساب'**
  String get authRegisterStepAccount;

  /// Register step 2 title.
  ///
  /// In ar, this message translates to:
  /// **'بيانات المنشأة'**
  String get authRegisterStepCompany;

  /// Register step 3 title.
  ///
  /// In ar, this message translates to:
  /// **'العنوان والموافقة'**
  String get authRegisterStepAddress;

  /// Register submit button.
  ///
  /// In ar, this message translates to:
  /// **'إنشاء الحساب'**
  String get authRegisterSubmit;

  /// Prompt before the sign-in link.
  ///
  /// In ar, this message translates to:
  /// **'لدى منشأتك حساب؟'**
  String get authRegisterHaveAccount;

  /// Link back to sign in.
  ///
  /// In ar, this message translates to:
  /// **'سجّل الدخول'**
  String get authRegisterSignIn;

  /// Shown when server errors move the user back to a step.
  ///
  /// In ar, this message translates to:
  /// **'راجع الحقول المشار إليها ثم أعد المحاولة.'**
  String get authRegisterFixErrors;

  /// Consent checkbox: terms.
  ///
  /// In ar, this message translates to:
  /// **'أوافق على الشروط والأحكام'**
  String get authRegisterAcceptTerms;

  /// Consent checkbox: privacy.
  ///
  /// In ar, this message translates to:
  /// **'أوافق على سياسة الخصوصية'**
  String get authRegisterAcceptPrivacy;

  /// Consent checkbox error.
  ///
  /// In ar, this message translates to:
  /// **'يلزم الموافقة للمتابعة.'**
  String get authRegisterConsentRequired;

  /// Opens a legal document from a consent row.
  ///
  /// In ar, this message translates to:
  /// **'قراءة'**
  String get authRegisterReadDocument;

  /// Contact person name.
  ///
  /// In ar, this message translates to:
  /// **'الاسم الكامل'**
  String get authFieldsNameLabel;

  /// Phone field label.
  ///
  /// In ar, this message translates to:
  /// **'رقم الجوال'**
  String get authFieldsPhoneLabel;

  /// Phone field placeholder (after the fixed +966).
  ///
  /// In ar, this message translates to:
  /// **'5XXXXXXXX'**
  String get authFieldsPhoneHint;

  /// Password confirmation label.
  ///
  /// In ar, this message translates to:
  /// **'تأكيد كلمة المرور'**
  String get authFieldsPasswordConfirmLabel;

  /// Password checklist header.
  ///
  /// In ar, this message translates to:
  /// **'يجب أن تحتوي كلمة المرور على:'**
  String get authPasswordRulesTitle;

  /// Password rule.
  ///
  /// In ar, this message translates to:
  /// **'8 أحرف على الأقل'**
  String get authPasswordRuleLength;

  /// Password rule.
  ///
  /// In ar, this message translates to:
  /// **'حرف إنجليزي صغير'**
  String get authPasswordRuleLower;

  /// Password rule.
  ///
  /// In ar, this message translates to:
  /// **'حرف إنجليزي كبير'**
  String get authPasswordRuleUpper;

  /// Password rule.
  ///
  /// In ar, this message translates to:
  /// **'رقم واحد على الأقل'**
  String get authPasswordRuleDigit;

  /// Password rule.
  ///
  /// In ar, this message translates to:
  /// **'رمز خاص مثل @ أو #'**
  String get authPasswordRuleSymbol;

  /// Organisation name.
  ///
  /// In ar, this message translates to:
  /// **'اسم المنشأة'**
  String get organizationFieldsNameLabel;

  /// CR label.
  ///
  /// In ar, this message translates to:
  /// **'رقم السجل التجاري'**
  String get organizationFieldsCrLabel;

  /// CR helper.
  ///
  /// In ar, this message translates to:
  /// **'10 أرقام'**
  String get organizationFieldsCrHelper;

  /// Region picker.
  ///
  /// In ar, this message translates to:
  /// **'المنطقة'**
  String get organizationFieldsRegionLabel;

  /// City field.
  ///
  /// In ar, this message translates to:
  /// **'المدينة'**
  String get organizationFieldsCityLabel;

  /// VAT switch.
  ///
  /// In ar, this message translates to:
  /// **'المنشأة مسجّلة في ضريبة القيمة المضافة'**
  String get organizationFieldsVatRegisteredLabel;

  /// VAT number field.
  ///
  /// In ar, this message translates to:
  /// **'الرقم الضريبي'**
  String get organizationFieldsVatLabel;

  /// VAT helper.
  ///
  /// In ar, this message translates to:
  /// **'15 رقماً يبدأ وينتهي بالرقم 3'**
  String get organizationFieldsVatHelper;

  /// Legal name AR.
  ///
  /// In ar, this message translates to:
  /// **'الاسم القانوني بالعربية'**
  String get organizationFieldsLegalNameArLabel;

  /// Legal name EN.
  ///
  /// In ar, this message translates to:
  /// **'الاسم القانوني بالإنجليزية'**
  String get organizationFieldsLegalNameEnLabel;

  /// Website field.
  ///
  /// In ar, this message translates to:
  /// **'الموقع الإلكتروني'**
  String get organizationFieldsWebsiteLabel;

  /// Website placeholder.
  ///
  /// In ar, this message translates to:
  /// **'https://example.sa'**
  String get organizationFieldsWebsiteHint;

  /// Categories multi-select.
  ///
  /// In ar, this message translates to:
  /// **'الفئات'**
  String get organizationFieldsCategoriesLabel;

  /// Categories helper.
  ///
  /// In ar, this message translates to:
  /// **'اختر حتى 20 فئة تعمل فيها منشأتك.'**
  String get organizationFieldsCategoriesHelper;

  /// Suggestions switch.
  ///
  /// In ar, this message translates to:
  /// **'أظهر منشأتي في اقتراحات طارحي المنافسات'**
  String get organizationFieldsVisibleInSuggestions;

  /// National address section.
  ///
  /// In ar, this message translates to:
  /// **'العنوان الوطني'**
  String get organizationAddressTitle;

  /// National address helper.
  ///
  /// In ar, this message translates to:
  /// **'اختياري الآن، ويلزم لاحقاً لإصدار الفواتير.'**
  String get organizationAddressHelper;

  /// Address part.
  ///
  /// In ar, this message translates to:
  /// **'رقم المبنى'**
  String get organizationAddressBuildingNumber;

  /// Address part.
  ///
  /// In ar, this message translates to:
  /// **'الشارع'**
  String get organizationAddressStreet;

  /// Address part.
  ///
  /// In ar, this message translates to:
  /// **'الحي'**
  String get organizationAddressDistrict;

  /// Address part.
  ///
  /// In ar, this message translates to:
  /// **'الرمز البريدي'**
  String get organizationAddressPostalCode;

  /// Address part.
  ///
  /// In ar, this message translates to:
  /// **'الرقم الإضافي'**
  String get organizationAddressAdditionalNumber;

  /// Address part.
  ///
  /// In ar, this message translates to:
  /// **'العنوان المختصر'**
  String get organizationAddressShortAddress;

  /// OTP screen title.
  ///
  /// In ar, this message translates to:
  /// **'تأكيد البريد الإلكتروني'**
  String get authVerifyTitle;

  /// OTP screen message; the e-mail is an LTR island.
  ///
  /// In ar, this message translates to:
  /// **'أرسلنا رمز تحقق من 6 أرقام إلى {email}.'**
  String authVerifyMessage(String email);

  /// OTP submit.
  ///
  /// In ar, this message translates to:
  /// **'تأكيد'**
  String get authVerifySubmit;

  /// OTP input label.
  ///
  /// In ar, this message translates to:
  /// **'رمز التحقق'**
  String get authOtpLabel;

  /// OTP expiry countdown.
  ///
  /// In ar, this message translates to:
  /// **'تنتهي صلاحية الرمز خلال {time}'**
  String authOtpExpiresIn(String time);

  /// OTP expired.
  ///
  /// In ar, this message translates to:
  /// **'انتهت صلاحية الرمز. اطلب رمزاً جديداً.'**
  String get authOtpExpired;

  /// Resend button.
  ///
  /// In ar, this message translates to:
  /// **'إعادة إرسال الرمز'**
  String get authOtpResend;

  /// Resend cooldown.
  ///
  /// In ar, this message translates to:
  /// **'إعادة الإرسال بعد {seconds} ث'**
  String authOtpResendIn(int seconds);

  /// Toast after resend.
  ///
  /// In ar, this message translates to:
  /// **'أرسلنا رمزاً جديداً.'**
  String get authOtpResent;

  /// Forgot screen title.
  ///
  /// In ar, this message translates to:
  /// **'استعادة كلمة المرور'**
  String get authForgotTitle;

  /// Forgot screen message.
  ///
  /// In ar, this message translates to:
  /// **'أدخل البريد الإلكتروني المسجّل وسنرسل إليك رمز تحقق.'**
  String get authForgotMessage;

  /// Forgot submit.
  ///
  /// In ar, this message translates to:
  /// **'إرسال الرمز'**
  String get authForgotSubmit;

  /// Neutral message after forgot (never reveals the account).
  ///
  /// In ar, this message translates to:
  /// **'إذا كان البريد مسجلاً فستصلك رسالة برمز التحقق.'**
  String get authForgotSent;

  /// Reset screen title.
  ///
  /// In ar, this message translates to:
  /// **'تعيين كلمة مرور جديدة'**
  String get authResetTitle;

  /// Reset screen message.
  ///
  /// In ar, this message translates to:
  /// **'أدخل الرمز المرسل إلى {email} ثم اختر كلمة مرور جديدة.'**
  String authResetMessage(String email);

  /// Early OTP check succeeded.
  ///
  /// In ar, this message translates to:
  /// **'الرمز صحيح.'**
  String get authResetCodeValid;

  /// New password label.
  ///
  /// In ar, this message translates to:
  /// **'كلمة المرور الجديدة'**
  String get authResetNewPasswordLabel;

  /// Reset submit.
  ///
  /// In ar, this message translates to:
  /// **'حفظ كلمة المرور'**
  String get authResetSubmit;

  /// Toast after a reset.
  ///
  /// In ar, this message translates to:
  /// **'تم تغيير كلمة المرور. سجّل الدخول.'**
  String get authResetDone;

  /// Link to the terms.
  ///
  /// In ar, this message translates to:
  /// **'الشروط والأحكام'**
  String get legalLinksTerms;

  /// Link to the privacy policy.
  ///
  /// In ar, this message translates to:
  /// **'سياسة الخصوصية'**
  String get legalLinksPrivacy;

  /// Link to the competition rules.
  ///
  /// In ar, this message translates to:
  /// **'قواعد المنافسات'**
  String get legalLinksCompetitionRules;

  /// Legal document version.
  ///
  /// In ar, this message translates to:
  /// **'الإصدار {version}'**
  String legalVersion(String version);

  /// Legal document date.
  ///
  /// In ar, this message translates to:
  /// **'نُشر في {date}'**
  String legalPublishedOn(String date);

  /// Legal document not published (404).
  ///
  /// In ar, this message translates to:
  /// **'هذه الوثيقة غير متاحة حالياً.'**
  String get legalUnavailable;

  /// Direction name.
  ///
  /// In ar, this message translates to:
  /// **'{direction, select, tender{مناقصة} auction{مزايدة} other{منافسة}}'**
  String competitionsDirectionLabel(String direction);

  /// Direction rule on the direction chip.
  ///
  /// In ar, this message translates to:
  /// **'{direction, select, tender{الأقل سعراً يفوز} auction{الأعلى سعراً يفوز} other{منافسة}}'**
  String competitionsDirectionRule(String direction);

  /// Format chip: live.
  ///
  /// In ar, this message translates to:
  /// **'مباشرة'**
  String get competitionsFormatLive;

  /// Format chip: sealed.
  ///
  /// In ar, this message translates to:
  /// **'بظرف مغلق'**
  String get competitionsFormatSealed;

  /// Status chip.
  ///
  /// In ar, this message translates to:
  /// **'مسودة'**
  String get competitionsStatusDraft;

  /// Status chip.
  ///
  /// In ar, this message translates to:
  /// **'مجدولة'**
  String get competitionsStatusScheduled;

  /// Status chip (live, initial or open).
  ///
  /// In ar, this message translates to:
  /// **'مفتوحة للعروض'**
  String get competitionsStatusLive;

  /// Status chip (live, final window).
  ///
  /// In ar, this message translates to:
  /// **'فترة التسعير النهائية'**
  String get competitionsStatusFinalWindow;

  /// Status chip (live, sealed).
  ///
  /// In ar, this message translates to:
  /// **'مفتوحة · عروض مغلقة'**
  String get competitionsStatusLiveSealed;

  /// Status chip (closed).
  ///
  /// In ar, this message translates to:
  /// **'قيد التقييم'**
  String get competitionsStatusClosed;

  /// Status chip (bafo_round).
  ///
  /// In ar, this message translates to:
  /// **'جولة العرض النهائي'**
  String get competitionsStatusBafoRound;

  /// Status chip.
  ///
  /// In ar, this message translates to:
  /// **'تمت الترسية'**
  String get competitionsStatusAwarded;

  /// Status chip.
  ///
  /// In ar, this message translates to:
  /// **'أُغلقت دون ترسية'**
  String get competitionsStatusNotAwarded;

  /// Status chip.
  ///
  /// In ar, this message translates to:
  /// **'ملغاة'**
  String get competitionsStatusCancelled;

  /// Status chip for an unknown value.
  ///
  /// In ar, this message translates to:
  /// **'حالة غير معروفة'**
  String get competitionsStatusUnknown;

  /// Overlay pill: under 10 minutes left.
  ///
  /// In ar, this message translates to:
  /// **'تُغلق قريباً'**
  String get competitionsStatusClosingSoon;

  /// Overlay pill: the close was extended.
  ///
  /// In ar, this message translates to:
  /// **'مُدّد الإغلاق'**
  String get competitionsStatusExtended;

  /// Countdown label before opening.
  ///
  /// In ar, this message translates to:
  /// **'تبدأ خلال'**
  String get competitionsCountdownOpensIn;

  /// Countdown label while live.
  ///
  /// In ar, this message translates to:
  /// **'تُغلق خلال'**
  String get competitionsCountdownClosesIn;

  /// Countdown label for invitees.
  ///
  /// In ar, this message translates to:
  /// **'الانضمام قبل'**
  String get competitionsCountdownJoinBefore;

  /// Countdown label during a BAFO round.
  ///
  /// In ar, this message translates to:
  /// **'تنتهي جولة العرض النهائي خلال'**
  String get competitionsCountdownBafoEndsIn;

  /// At zero, until the server confirms the close.
  ///
  /// In ar, this message translates to:
  /// **'جارٍ الإغلاق…'**
  String get competitionsCountdownClosing;

  /// Screen-reader announcement at 10, 5 and 1 minutes.
  ///
  /// In ar, this message translates to:
  /// **'{count, plural, =1{تبقّت دقيقة واحدة على الإغلاق} =2{تبقّت دقيقتان على الإغلاق} few{تبقّت {count} دقائق على الإغلاق} many{تبقّت {count} دقيقة على الإغلاق} other{تبقّت {count} دقيقة على الإغلاق}}'**
  String competitionsCountdownAnnounceMinutes(int count);

  /// Extension banner under a countdown.
  ///
  /// In ar, this message translates to:
  /// **'{count, plural, =1{مُدّد وقت الإغلاق مرة واحدة.} =2{مُدّد وقت الإغلاق مرتين.} few{مُدّد وقت الإغلاق {count} مرات.} many{مُدّد وقت الإغلاق {count} مرة.} other{مُدّد وقت الإغلاق {count} مرة.}}'**
  String competitionsExtensionBanner(int count);

  /// hard_stop_at when auto-extend is on.
  ///
  /// In ar, this message translates to:
  /// **'أقصى موعد للإغلاق: {time}'**
  String competitionsLatestPossibleClose(String time);

  /// Rules summary card title.
  ///
  /// In ar, this message translates to:
  /// **'قواعد المنافسة'**
  String get rulesSummaryTitle;

  /// Standing: leading.
  ///
  /// In ar, this message translates to:
  /// **'عرضك هو العرض المتصدر'**
  String get liveStatusLeading;

  /// Standing: not leading (amber, never red).
  ///
  /// In ar, this message translates to:
  /// **'{direction, select, tender{عرضك ليس العرض المتصدر. خفّض عرضك لتنافس.} auction{عرضك ليس العرض المتصدر. ارفع عرضك لتنافس.} other{عرضك ليس العرض المتصدر.}}'**
  String liveStatusNotLeading(String direction);

  /// Standing: rank.
  ///
  /// In ar, this message translates to:
  /// **'ترتيبك {rank} من {count}'**
  String liveStatusRank(int rank, int count);

  /// Standing hidden by the rules.
  ///
  /// In ar, this message translates to:
  /// **'استُلم عرضك. لا تُظهر هذه المنافسة الترتيب.'**
  String get liveStatusHidden;

  /// Standing before the first offer.
  ///
  /// In ar, this message translates to:
  /// **'لم تقدّم عرضاً بعد.'**
  String get liveStatusNoOffer;

  /// Compact standing badge: leading.
  ///
  /// In ar, this message translates to:
  /// **'متصدر'**
  String get liveBadgeLeading;

  /// Compact standing badge: not leading.
  ///
  /// In ar, this message translates to:
  /// **'غير متصدر'**
  String get liveBadgeNotLeading;

  /// Access chip.
  ///
  /// In ar, this message translates to:
  /// **'بانتظار انضمامك'**
  String get invitationsAccessJoinRequired;

  /// Access chip.
  ///
  /// In ar, this message translates to:
  /// **'تتطلب باقة'**
  String get invitationsAccessPlanRequired;

  /// Access chip.
  ///
  /// In ar, this message translates to:
  /// **'منضم'**
  String get invitationsAccessFull;

  /// Access chip.
  ///
  /// In ar, this message translates to:
  /// **'للاطلاع فقط'**
  String get invitationsAccessReadOnly;

  /// Access chip.
  ///
  /// In ar, this message translates to:
  /// **'غير متاحة'**
  String get invitationsAccessUnavailable;

  /// Badge shown only to the covered participant.
  ///
  /// In ar, this message translates to:
  /// **'رسوم مغطّاة'**
  String get sponsorshipBadgeFeesCovered;

  /// Sponsored line under the fees-covered badge.
  ///
  /// In ar, this message translates to:
  /// **'تغطي {issuer} رسوم مشاركتكم في هذه المنافسة.'**
  String sponsorshipCoveredBy(String issuer);

  /// Entitlement-only notice (no purchase in the app).
  ///
  /// In ar, this message translates to:
  /// **'تُدفع رسوم المشاركة لهذه المنافسة من لوحة تحكم بافو على الويب. حُفظت المسودة.'**
  String get sponsorshipManagedOnWeb;

  /// Result chip.
  ///
  /// In ar, this message translates to:
  /// **'تمت الترسية عليكم'**
  String get awardOutcomeWon;

  /// Result chip.
  ///
  /// In ar, this message translates to:
  /// **'لم يتم اختياركم'**
  String get awardOutcomeNotSelected;

  /// Result chip.
  ///
  /// In ar, this message translates to:
  /// **'أُغلقت دون ترسية'**
  String get awardOutcomeNotAwarded;

  /// Competition detail app bar.
  ///
  /// In ar, this message translates to:
  /// **'تفاصيل المنافسة'**
  String get competitionsDetailTitle;

  /// Issuer row.
  ///
  /// In ar, this message translates to:
  /// **'طارح المنافسة'**
  String get competitionsDetailIssuer;

  /// Category row.
  ///
  /// In ar, this message translates to:
  /// **'الفئة'**
  String get competitionsDetailCategory;

  /// Region row.
  ///
  /// In ar, this message translates to:
  /// **'المنطقة'**
  String get competitionsDetailRegion;

  /// Reference number row.
  ///
  /// In ar, this message translates to:
  /// **'الرقم المرجعي'**
  String get competitionsDetailReference;

  /// Description section.
  ///
  /// In ar, this message translates to:
  /// **'الوصف'**
  String get competitionsDetailDescription;

  /// Schedule section.
  ///
  /// In ar, this message translates to:
  /// **'المواعيد'**
  String get competitionsDetailSchedule;

  /// Documents section.
  ///
  /// In ar, this message translates to:
  /// **'المستندات'**
  String get competitionsDetailDocuments;

  /// Empty documents section.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد مستندات.'**
  String get competitionsDetailNoDocuments;

  /// Competition not found (never reveals existence).
  ///
  /// In ar, this message translates to:
  /// **'هذه المنافسة غير موجودة أو ليست لديك صلاحية الوصول إليها.'**
  String get competitionsDetailNotFound;

  /// Cancelled competition.
  ///
  /// In ar, this message translates to:
  /// **'سبب الإلغاء: {reason}'**
  String competitionsDetailCancelled(String reason);

  /// Not awarded competition.
  ///
  /// In ar, this message translates to:
  /// **'سبب الإغلاق دون ترسية: {reason}'**
  String competitionsDetailNotAwarded(String reason);

  /// Schedule row.
  ///
  /// In ar, this message translates to:
  /// **'بدء استقبال العروض'**
  String get competitionsScheduleOpensAt;

  /// Schedule row.
  ///
  /// In ar, this message translates to:
  /// **'موعد الإغلاق'**
  String get competitionsScheduleClosesAt;

  /// Schedule row.
  ///
  /// In ar, this message translates to:
  /// **'آخر موعد للانضمام'**
  String get competitionsScheduleJoinDeadline;

  /// Schedule row.
  ///
  /// In ar, this message translates to:
  /// **'بدء فترة التسعير النهائية'**
  String get competitionsScheduleFinalWindow;

  /// Draft without an opening time.
  ///
  /// In ar, this message translates to:
  /// **'عند النشر'**
  String get competitionsScheduleOpensOnPublish;

  /// Entitlement-only notice: no link, no button.
  ///
  /// In ar, this message translates to:
  /// **'تُدار الاشتراكات والمدفوعات من لوحة تحكم بافو على الويب.'**
  String get billingManagedOnWeb;

  /// Invoices are not shown in the app.
  ///
  /// In ar, this message translates to:
  /// **'الفواتير متاحة في لوحة تحكم بافو على الويب.'**
  String get billingInvoicesOnWeb;

  /// M58 title.
  ///
  /// In ar, this message translates to:
  /// **'الباقة والاشتراك'**
  String get billingStatusTitle;

  /// M58 row.
  ///
  /// In ar, this message translates to:
  /// **'الباقة'**
  String get billingStatusPlan;

  /// M58 row.
  ///
  /// In ar, this message translates to:
  /// **'نوع الاشتراك'**
  String get billingStatusSource;

  /// M58 row.
  ///
  /// In ar, this message translates to:
  /// **'الحالة'**
  String get billingStatusState;

  /// M58 row.
  ///
  /// In ar, this message translates to:
  /// **'ينتهي في'**
  String get billingStatusEnds;

  /// M58 row.
  ///
  /// In ar, this message translates to:
  /// **'المقاعد'**
  String get billingStatusSeats;

  /// Seats used of total.
  ///
  /// In ar, this message translates to:
  /// **'{used} من {total}'**
  String billingStatusSeatsValue(int used, int total);

  /// Days left of the subscription.
  ///
  /// In ar, this message translates to:
  /// **'{count, plural, =0{ينتهي اليوم} =1{يوم واحد متبقٍ} =2{يومان متبقيان} few{{count} أيام متبقية} many{{count} يوماً متبقياً} other{{count} يوم متبقٍ}}'**
  String billingStatusDaysLeft(int count);

  /// M58 without a subscription.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد باقة فعّالة لمنشأتكم.'**
  String get billingStatusNoPlan;

  /// M58 upcoming subscription.
  ///
  /// In ar, this message translates to:
  /// **'الاشتراك التالي'**
  String get billingStatusUpcoming;

  /// Subscription source.
  ///
  /// In ar, this message translates to:
  /// **'اشتراك مدفوع'**
  String get billingSourcePaid;

  /// Subscription source.
  ///
  /// In ar, this message translates to:
  /// **'تجربة مجانية'**
  String get billingSourceTrial;

  /// Subscription source.
  ///
  /// In ar, this message translates to:
  /// **'مقدّم من بافو'**
  String get billingSourceGrant;

  /// Subscription status.
  ///
  /// In ar, this message translates to:
  /// **'فعّال'**
  String get billingSubscriptionActive;

  /// Subscription status.
  ///
  /// In ar, this message translates to:
  /// **'بانتظار الدفع'**
  String get billingSubscriptionPendingPayment;

  /// Subscription status.
  ///
  /// In ar, this message translates to:
  /// **'منتهٍ'**
  String get billingSubscriptionExpired;

  /// Subscription status.
  ///
  /// In ar, this message translates to:
  /// **'استُبدل'**
  String get billingSubscriptionSuperseded;

  /// Subscription status.
  ///
  /// In ar, this message translates to:
  /// **'ملغى'**
  String get billingSubscriptionCancelled;

  /// Subscription status (unknown value).
  ///
  /// In ar, this message translates to:
  /// **'غير معروف'**
  String get billingSubscriptionUnknown;

  /// errors.bad_request (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'تعذّر فهم الطلب.'**
  String get errorsBadRequest;

  /// errors.forbidden (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'ليست لديك صلاحية لهذه الصفحة. تواصل مع مالك الحساب.'**
  String get errorsForbidden;

  /// errors.not_found (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'العنصر المطلوب غير موجود أو ليست لديك صلاحية الوصول إليه.'**
  String get errorsNotFound;

  /// errors.conflict (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'تعذّر تنفيذ الطلب بسبب تعارض. حدّث الصفحة وحاول مرة أخرى.'**
  String get errorsConflict;

  /// errors.payload_too_large (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'حجم الطلب أكبر من المسموح.'**
  String get errorsPayloadTooLarge;

  /// errors.unsupported_media_type (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'نوع المحتوى غير مدعوم.'**
  String get errorsUnsupportedMediaType;

  /// errors.validation_failed (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'راجع البيانات المُدخلة.'**
  String get errorsValidationFailed;

  /// errors.app_version_unsupported (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'هذا الإصدار من التطبيق لم يعد مدعوماً. حدّث التطبيق للمتابعة.'**
  String get errorsAppVersionUnsupported;

  /// errors.too_many_requests (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'طلبات كثيرة. حاول مرة أخرى بعد قليل.'**
  String get errorsTooManyRequests;

  /// errors.service_unavailable (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'الخدمة غير متاحة مؤقتاً. حاول مرة أخرى بعد قليل.'**
  String get errorsServiceUnavailable;

  /// errors.maintenance (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'بافو تحت الصيانة. حاول مرة أخرى بعد قليل.'**
  String get errorsMaintenance;

  /// errors.idempotency_key_required (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'تعذّر إرسال الطلب. حاول مرة أخرى.'**
  String get errorsIdempotencyKeyRequired;

  /// errors.idempotency_key_reused (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'تعذّر تأكيد الطلب. أكّد العملية من جديد.'**
  String get errorsIdempotencyKeyReused;

  /// errors.idempotency_request_in_progress (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'ما زال طلبك السابق قيد المعالجة. انتظر قليلاً.'**
  String get errorsIdempotencyRequestInProgress;

  /// errors.invalid_state_transition (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'لم يعد هذا الإجراء متاحاً في الحالة الحالية.'**
  String get errorsInvalidStateTransition;

  /// errors.file_type_not_allowed (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'نوع الملف غير مسموح.'**
  String get errorsFileTypeNotAllowed;

  /// errors.file_too_large (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'حجم الملف أكبر من المسموح.'**
  String get errorsFileTooLarge;

  /// errors.bad_response (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'وصل رد غير متوقع من الخادم. حاول مرة أخرى.'**
  String get errorsBadResponse;

  /// errors.offline (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'لا يوجد اتصال بالإنترنت.'**
  String get errorsOffline;

  /// errors.invalid_credentials (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'البريد الإلكتروني أو كلمة المرور غير صحيحة.'**
  String get errorsInvalidCredentials;

  /// errors.email_not_verified (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'أكّد بريدك الإلكتروني للمتابعة. أرسلنا إليك رمز تحقق.'**
  String get errorsEmailNotVerified;

  /// errors.account_inactive (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'حسابك غير مفعّل في هذه المنشأة. تواصل مع مالك الحساب.'**
  String get errorsAccountInactive;

  /// errors.organization_suspended (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'أُوقف حساب منشأتكم مؤقتاً. تواصل مع دعم بافو.'**
  String get errorsOrganizationSuspended;

  /// errors.otp_invalid (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'الرمز غير صحيح.'**
  String get errorsOtpInvalid;

  /// errors.otp_expired (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'انتهت صلاحية الرمز. اطلب رمزاً جديداً.'**
  String get errorsOtpExpired;

  /// errors.otp_too_many_attempts (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'أُبطل الرمز بعد محاولات كثيرة. اطلب رمزاً جديداً.'**
  String get errorsOtpTooManyAttempts;

  /// errors.otp_resend_cooldown (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'انتظر قليلاً قبل طلب رمز جديد.'**
  String get errorsOtpResendCooldown;

  /// errors.password_incorrect (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'كلمة المرور الحالية غير صحيحة.'**
  String get errorsPasswordIncorrect;

  /// errors.team_invitation_invalid (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'رابط الدعوة غير صالح أو منتهٍ. اطلب من مدير الحساب إعادة الإرسال.'**
  String get errorsTeamInvitationInvalid;

  /// errors.seat_limit_reached (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'اكتملت مقاعد باقتكم.'**
  String get errorsSeatLimitReached;

  /// errors.cannot_modify_owner (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'لا يمكن تعديل مالك الحساب أو إزالته.'**
  String get errorsCannotModifyOwner;

  /// errors.cannot_modify_self (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'لا يمكنك تعديل عضويتك أو إزالتها بنفسك.'**
  String get errorsCannotModifySelf;

  /// errors.account_deletion_blocked (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'لا يمكن حذف الحساب قبل انتهاء المنافسات والمشاركات المفتوحة.'**
  String get errorsAccountDeletionBlocked;

  /// errors.account_deletion_pending (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'يوجد طلب حذف قيد المعالجة.'**
  String get errorsAccountDeletionPending;

  /// errors.invitation_email_mismatch (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'يجب التسجيل بالبريد الإلكتروني المدعو.'**
  String get errorsInvitationEmailMismatch;

  /// errors.competition_not_editable (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'لا يمكن تعديل هذه البيانات في حالة المنافسة الحالية.'**
  String get errorsCompetitionNotEditable;

  /// errors.issuer_plan_required (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'تحتاج منشأتكم إلى باقة فعّالة لطرح المنافسات.'**
  String get errorsIssuerPlanRequired;

  /// errors.auction_not_enabled (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'المزايدات غير مفعّلة لمنشأتكم. تواصل مع بافو.'**
  String get errorsAuctionNotEnabled;

  /// errors.min_participants_not_met (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'عدد المدعوين أقل من الحد الأدنى للمتنافسين.'**
  String get errorsMinParticipantsNotMet;

  /// errors.max_participants_exceeded (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'تجاوزت الحد الأقصى لعدد المتنافسين.'**
  String get errorsMaxParticipantsExceeded;

  /// errors.live_event_capacity_reached (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'بلغ عدد المنافسات المباشرة في هذا الوقت الحد الأقصى. اختر وقتاً آخر.'**
  String get errorsLiveEventCapacityReached;

  /// errors.extend_invalid (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'موعد الإغلاق الجديد غير مسموح.'**
  String get errorsExtendInvalid;

  /// errors.invitation_cutoff_passed (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'انتهى موعد إرسال الدعوات لهذه المنافسة.'**
  String get errorsInvitationCutoffPassed;

  /// errors.invitation_invalid (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'رابط الدعوة غير صالح أو منتهٍ.'**
  String get errorsInvitationInvalid;

  /// errors.invitation_belongs_to_another_organization (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'هذه الدعوة مرتبطة بمنشأة أخرى.'**
  String get errorsInvitationBelongsToAnotherOrganization;

  /// errors.join_deadline_passed (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'انتهى موعد الانضمام إلى هذه المنافسة.'**
  String get errorsJoinDeadlinePassed;

  /// errors.already_participating (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'منشأتكم منضمة إلى هذه المنافسة بالفعل.'**
  String get errorsAlreadyParticipating;

  /// errors.terms_not_accepted (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'يلزم قبول قواعد المنافسة للانضمام.'**
  String get errorsTermsNotAccepted;

  /// errors.not_a_participant (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'هذا الإجراء متاح للمنشآت المنضمة إلى المنافسة فقط.'**
  String get errorsNotAParticipant;

  /// errors.comments_closed (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'الاستفسارات مغلقة لهذه المنافسة.'**
  String get errorsCommentsClosed;

  /// errors.report_not_available (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'التقرير غير متاح قبل إغلاق المنافسة.'**
  String get errorsReportNotAvailable;

  /// errors.offer_amount_invalid (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'أدخل مبلغاً صحيحاً أكبر من صفر.'**
  String get errorsOfferAmountInvalid;

  /// errors.offer_amount_too_large (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'المبلغ أكبر من الحد المسموح.'**
  String get errorsOfferAmountTooLarge;

  /// errors.offer_granularity (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'المبلغ لا يطابق دقة المبالغ في هذه المنافسة.'**
  String get errorsOfferGranularity;

  /// errors.offer_not_accepting (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'لا تستقبل المنافسة العروض الآن.'**
  String get errorsOfferNotAccepting;

  /// errors.offer_closed (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'أُغلق استقبال العروض.'**
  String get errorsOfferClosed;

  /// errors.offer_not_shortlisted (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'منشأتكم ليست ضمن القائمة المختصرة لجولة العرض النهائي.'**
  String get errorsOfferNotShortlisted;

  /// errors.offer_bafo_already_submitted (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'قدّمتم عرضكم النهائي بالفعل.'**
  String get errorsOfferBafoAlreadySubmitted;

  /// errors.offer_start_price (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'العرض لا يستوفي سعر البداية.'**
  String get errorsOfferStartPrice;

  /// errors.offer_step_not_met (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'العرض لا يستوفي الحد الأدنى للتحسين.'**
  String get errorsOfferStepNotMet;

  /// errors.offer_bafo_worse_than_reference (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'لا يمكن أن يكون عرضك النهائي أسوأ من عرضك الأخير.'**
  String get errorsOfferBafoWorseThanReference;

  /// errors.offer_outlier_confirm_required (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'يختلف هذا العرض كثيراً عن عرضك الحالي. أكّده للمتابعة.'**
  String get errorsOfferOutlierConfirmRequired;

  /// errors.bafo_not_enabled (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'جولة العرض النهائي غير مفعّلة لهذه المنافسة.'**
  String get errorsBafoNotEnabled;

  /// errors.bafo_already_used (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'أُجريت جولة العرض النهائي لهذه المنافسة من قبل.'**
  String get errorsBafoAlreadyUsed;

  /// errors.award_participant_has_no_offer (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'لا يوجد عرض حالي لهذا المتنافس.'**
  String get errorsAwardParticipantHasNoOffer;

  /// errors.award_justification_required (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'يلزم مبرر لهذه الترسية.'**
  String get errorsAwardJustificationRequired;

  /// errors.award_reserve_confirmation_required (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'يلزم تأكيد الترسية رغم عدم تحقق السعر المستهدف أو الحد الأدنى المقبول.'**
  String get errorsAwardReserveConfirmationRequired;

  /// errors.plan_required (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'تحتاج منشأتكم إلى باقة فعّالة للانضمام.'**
  String get errorsPlanRequired;

  /// errors.purchase_not_available_on_platform (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'تُدار الاشتراكات والمدفوعات من لوحة تحكم بافو على الويب.'**
  String get errorsPurchaseNotAvailableOnPlatform;

  /// errors.billing_profile_incomplete (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'بيانات الفوترة للمنشأة غير مكتملة.'**
  String get errorsBillingProfileIncomplete;

  /// errors.sponsorship_not_enabled (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'تغطية رسوم المشاركة غير مفعّلة لمنشأتكم.'**
  String get errorsSponsorshipNotEnabled;

  /// errors.sponsorship_payment_required (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'تُدفع رسوم المشاركة لهذه المنافسة من لوحة تحكم بافو على الويب.'**
  String get errorsSponsorshipPaymentRequired;

  /// errors.invoice_pdf_not_ready (CONVENTIONS §8).
  ///
  /// In ar, this message translates to:
  /// **'الفاتورة غير جاهزة بعد.'**
  String get errorsInvoicePdfNotReady;

  /// Free-text note field (reasons).
  ///
  /// In ar, this message translates to:
  /// **'ملاحظة'**
  String get commonNoteLabel;

  /// M14 greeting with the user name.
  ///
  /// In ar, this message translates to:
  /// **'مرحباً، {name}'**
  String homeGreeting(String name);

  /// M14 greeting before the account is loaded.
  ///
  /// In ar, this message translates to:
  /// **'مرحباً'**
  String get homeGreetingFallback;

  /// M14 issuer stat tiles section.
  ///
  /// In ar, this message translates to:
  /// **'المنافسات التي تطرحونها'**
  String get homeIssuerSectionTitle;

  /// M14 participant stat tiles section.
  ///
  /// In ar, this message translates to:
  /// **'مشاركاتكم'**
  String get homeParticipantSectionTitle;

  /// Home issuer tile: active_competitions.
  ///
  /// In ar, this message translates to:
  /// **'منافسات نشطة'**
  String get homeStatsActiveCompetitions;

  /// Home issuer tile: draft_competitions.
  ///
  /// In ar, this message translates to:
  /// **'مسودات'**
  String get homeStatsDraftCompetitions;

  /// Home issuer tile: live_now.
  ///
  /// In ar, this message translates to:
  /// **'مفتوحة للعروض الآن'**
  String get homeStatsLiveNow;

  /// Home issuer tile: awaiting_award.
  ///
  /// In ar, this message translates to:
  /// **'بانتظار الترسية'**
  String get homeStatsAwaitingAward;

  /// Home issuer tile: offers_received_30d.
  ///
  /// In ar, this message translates to:
  /// **'عروض مستلمة خلال 30 يوماً'**
  String get homeStatsOffersReceived30d;

  /// Home participant tile: pending_invitations.
  ///
  /// In ar, this message translates to:
  /// **'دعوات بانتظار ردّكم'**
  String get homeStatsPendingInvitations;

  /// Home participant tile: active_participations.
  ///
  /// In ar, this message translates to:
  /// **'مشاركات نشطة'**
  String get homeStatsActiveParticipations;

  /// Home participant tile: offers_submitted_30d.
  ///
  /// In ar, this message translates to:
  /// **'عروض مقدّمة خلال 30 يوماً'**
  String get homeStatsOffersSubmitted30d;

  /// Home participant tile: awards_won.
  ///
  /// In ar, this message translates to:
  /// **'ترسيات لصالحكم'**
  String get homeStatsAwardsWon;

  /// M14 quick actions section.
  ///
  /// In ar, this message translates to:
  /// **'إجراءات سريعة'**
  String get homeQuickActionsTitle;

  /// M14 quick action (S10: competitions.create and can_issue).
  ///
  /// In ar, this message translates to:
  /// **'طرح منافسة جديدة'**
  String get homeActionCreateCompetition;

  /// M14 quick action to the participating tab.
  ///
  /// In ar, this message translates to:
  /// **'استعراض مشاركاتي'**
  String get homeActionParticipating;

  /// M14 quick action to the issued competitions tab.
  ///
  /// In ar, this message translates to:
  /// **'استعراض منافساتي'**
  String get homeActionMyCompetitions;

  /// S10: create competition without can_issue (no purchase action on mobile).
  ///
  /// In ar, this message translates to:
  /// **'تحتاج إلى باقة فعّالة لطرح المنافسات.'**
  String get homeCreateNeedsPlan;

  /// M14 subscription card title.
  ///
  /// In ar, this message translates to:
  /// **'الباقة'**
  String get homeSubscriptionTitle;

  /// M14 subscription end date.
  ///
  /// In ar, this message translates to:
  /// **'ينتهي في {date}'**
  String homeSubscriptionEnds(String date);

  /// Accessible hint of the subscription card (opens M58).
  ///
  /// In ar, this message translates to:
  /// **'تفاصيل الباقة'**
  String get homeSubscriptionOpen;

  /// Home alert subscription_expiring with params.days_left.
  ///
  /// In ar, this message translates to:
  /// **'{count, plural, =0{ينتهي اشتراك منشأتكم اليوم.} =1{ينتهي اشتراك منشأتكم غداً.} =2{ينتهي اشتراك منشأتكم خلال يومين.} few{ينتهي اشتراك منشأتكم خلال {count} أيام.} many{ينتهي اشتراك منشأتكم خلال {count} يوماً.} other{ينتهي اشتراك منشأتكم خلال {count} يوم.}}'**
  String homeAlertSubscriptionExpiring(int count);

  /// Home alert subscription_expiring without days_left.
  ///
  /// In ar, this message translates to:
  /// **'ينتهي اشتراك منشأتكم قريباً.'**
  String get homeAlertSubscriptionExpiringSoon;

  /// Home alert subscription_expired.
  ///
  /// In ar, this message translates to:
  /// **'انتهى اشتراك منشأتكم.'**
  String get homeAlertSubscriptionExpired;

  /// Home alert plan_required (no purchase action, SCREENS §3.2).
  ///
  /// In ar, this message translates to:
  /// **'تحتاج منشأتكم إلى باقة فعّالة لطرح المنافسات والانضمام إليها.'**
  String get homeAlertPlanRequired;

  /// Home alert trial_available (no action button on mobile).
  ///
  /// In ar, this message translates to:
  /// **'تتوفر لمنشأتكم فترة تجريبية مجانية.'**
  String get homeAlertTrialAvailable;

  /// Home alert billing_profile_incomplete.
  ///
  /// In ar, this message translates to:
  /// **'بيانات الفوترة لمنشأتكم غير مكتملة.'**
  String get homeAlertBillingProfileIncomplete;

  /// Second line of the billing-profile alert when the user may edit the organisation.
  ///
  /// In ar, this message translates to:
  /// **'أكملوها من صفحة بيانات المنشأة.'**
  String get homeAlertBillingProfileAction;

  /// M14 recent activity section.
  ///
  /// In ar, this message translates to:
  /// **'آخر النشاطات'**
  String get homeActivityTitle;

  /// M14 empty activity list (home.activity.empty).
  ///
  /// In ar, this message translates to:
  /// **'لا توجد نشاطات بعد.'**
  String get homeActivityEmpty;

  /// home.activity.competition_created.
  ///
  /// In ar, this message translates to:
  /// **'أُنشئت مسودة «{title}».'**
  String homeActivityCompetitionCreated(String title);

  /// home.activity.competition_published.
  ///
  /// In ar, this message translates to:
  /// **'نُشرت «{title}».'**
  String homeActivityCompetitionPublished(String title);

  /// home.activity.competition_cancelled.
  ///
  /// In ar, this message translates to:
  /// **'أُلغيت «{title}».'**
  String homeActivityCompetitionCancelled(String title);

  /// home.activity.competition_closed.
  ///
  /// In ar, this message translates to:
  /// **'أُغلق استقبال العروض في «{title}».'**
  String homeActivityCompetitionClosed(String title);

  /// home.activity.award_issued.
  ///
  /// In ar, this message translates to:
  /// **'تمت ترسية «{title}».'**
  String homeActivityAwardIssued(String title);

  /// home.activity.invitation_joined.
  ///
  /// In ar, this message translates to:
  /// **'انضممتم إلى «{title}».'**
  String homeActivityInvitationJoined(String title);

  /// home.activity.member_added.
  ///
  /// In ar, this message translates to:
  /// **'أُضيف عضو إلى الفريق.'**
  String get homeActivityMemberAdded;

  /// home.activity.subscription_activated.
  ///
  /// In ar, this message translates to:
  /// **'فُعِّل اشتراك منشأتكم.'**
  String get homeActivitySubscriptionActivated;

  /// An activity action the app does not know yet.
  ///
  /// In ar, this message translates to:
  /// **'تحديث على حساب المنشأة.'**
  String get homeActivityOther;

  /// Activity actor shown for automatic actions (API actor name "system").
  ///
  /// In ar, this message translates to:
  /// **'النظام'**
  String get homeActivityActorSystem;

  /// S8: a refetch failed and stale data stays visible.
  ///
  /// In ar, this message translates to:
  /// **'تعذّر تحديث البيانات، وتظهر آخر بيانات محمّلة.'**
  String get homeRefreshFailed;

  /// M49 filter: all notifications.
  ///
  /// In ar, this message translates to:
  /// **'الكل'**
  String get notificationsFilterAll;

  /// M49 filter: unread notifications.
  ///
  /// In ar, this message translates to:
  /// **'غير المقروءة'**
  String get notificationsFilterUnread;

  /// M49 empty state of the unread filter.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد إشعارات غير مقروءة'**
  String get notificationsUnreadEmptyTitle;

  /// M49 empty state of the unread filter.
  ///
  /// In ar, this message translates to:
  /// **'اطّلعتم على جميع إشعاراتكم.'**
  String get notificationsUnreadEmptyMessage;

  /// M49 header action.
  ///
  /// In ar, this message translates to:
  /// **'تعليم الكل كمقروء'**
  String get notificationsMarkAllRead;

  /// M49 row action.
  ///
  /// In ar, this message translates to:
  /// **'تعليم كمقروء'**
  String get notificationsMarkRead;

  /// M49 row action and swipe label.
  ///
  /// In ar, this message translates to:
  /// **'حذف الإشعار'**
  String get notificationsDelete;

  /// M49 header action (destructive, confirmed).
  ///
  /// In ar, this message translates to:
  /// **'حذف كل الإشعارات'**
  String get notificationsDeleteAll;

  /// M49 delete-all confirmation title.
  ///
  /// In ar, this message translates to:
  /// **'حذف كل الإشعارات؟'**
  String get notificationsDeleteAllConfirmTitle;

  /// M49 delete-all confirmation message.
  ///
  /// In ar, this message translates to:
  /// **'ستُحذف جميع إشعاراتكم نهائياً.'**
  String get notificationsDeleteAllConfirmMessage;

  /// M49 toast after a delete.
  ///
  /// In ar, this message translates to:
  /// **'حُذف الإشعار.'**
  String get notificationsDeleted;

  /// M49 toast after mark all read.
  ///
  /// In ar, this message translates to:
  /// **'عُلّمت جميع الإشعارات كمقروءة.'**
  String get notificationsAllMarkedRead;

  /// M49 toast after delete all.
  ///
  /// In ar, this message translates to:
  /// **'حُذفت جميع الإشعارات.'**
  String get notificationsAllDeleted;

  /// Accessible label of the unread dot.
  ///
  /// In ar, this message translates to:
  /// **'غير مقروء'**
  String get notificationsUnreadLabel;

  /// Tooltip of the overflow menus.
  ///
  /// In ar, this message translates to:
  /// **'إجراءات إضافية'**
  String get notificationsMoreActions;

  /// Paging footer error.
  ///
  /// In ar, this message translates to:
  /// **'تعذّر تحميل المزيد.'**
  String get notificationsLoadMoreFailed;

  /// M50 push explainer title.
  ///
  /// In ar, this message translates to:
  /// **'فعّلوا التنبيهات الفورية'**
  String get notificationsPushTitle;

  /// M50 push explainer body.
  ///
  /// In ar, this message translates to:
  /// **'نرسل إليكم تنبيهاً عند وصول دعوة جديدة، وعند بدء استقبال العروض واقتراب الإغلاق، وعند صدور النتيجة. لا تتضمن التنبيهات أي مبالغ.'**
  String get notificationsPushBody;

  /// M50 allow button (then the OS prompt).
  ///
  /// In ar, this message translates to:
  /// **'تفعيل التنبيهات'**
  String get notificationsPushAllow;

  /// M50 dismiss button.
  ///
  /// In ar, this message translates to:
  /// **'ليس الآن'**
  String get notificationsPushNotNow;

  /// M50/M59 result: device registered.
  ///
  /// In ar, this message translates to:
  /// **'فُعِّلت التنبيهات الفورية على هذا الجهاز.'**
  String get notificationsPushEnabled;

  /// M50/M59 result: permission denied.
  ///
  /// In ar, this message translates to:
  /// **'التنبيهات الفورية مرفوضة. يمكنكم السماح بها من إعدادات الجهاز.'**
  String get notificationsPushDenied;

  /// M50/M59 result: no permission prompt or no device token.
  ///
  /// In ar, this message translates to:
  /// **'التنبيهات الفورية غير متاحة على هذا الجهاز حالياً، وتصلكم الإشعارات داخل التطبيق.'**
  String get notificationsPushUnavailable;

  /// M59 status: not registered yet.
  ///
  /// In ar, this message translates to:
  /// **'التنبيهات الفورية غير مفعّلة.'**
  String get notificationsPushOff;

  /// M59 row title.
  ///
  /// In ar, this message translates to:
  /// **'التنبيهات الفورية'**
  String get notificationsPushSettingTitle;

  /// M59 button to request the permission.
  ///
  /// In ar, this message translates to:
  /// **'تفعيل'**
  String get notificationsPushTurnOn;

  /// M51 section.
  ///
  /// In ar, this message translates to:
  /// **'حسابي'**
  String get accountSectionAccount;

  /// M51 section.
  ///
  /// In ar, this message translates to:
  /// **'المنشأة'**
  String get accountSectionOrganization;

  /// M51 section.
  ///
  /// In ar, this message translates to:
  /// **'التطبيق'**
  String get accountSectionApp;

  /// Membership role owner.
  ///
  /// In ar, this message translates to:
  /// **'المالك'**
  String get accountRoleOwner;

  /// Membership role admin.
  ///
  /// In ar, this message translates to:
  /// **'مدير'**
  String get accountRoleAdmin;

  /// Membership role member.
  ///
  /// In ar, this message translates to:
  /// **'عضو'**
  String get accountRoleMember;

  /// Verified organisation badge (read-only).
  ///
  /// In ar, this message translates to:
  /// **'منشأة موثّقة'**
  String get accountVerified;

  /// App version line.
  ///
  /// In ar, this message translates to:
  /// **'الإصدار {version}'**
  String accountVersion(String version);

  /// M51 without Me (offline start).
  ///
  /// In ar, this message translates to:
  /// **'تعذّر تحميل بيانات الحساب. اسحبوا الشاشة للتحديث عند توفر الاتصال.'**
  String get accountMeUnavailable;

  /// Toast after a successful save.
  ///
  /// In ar, this message translates to:
  /// **'حُفظت التغييرات.'**
  String get accountSaved;

  /// Unsaved-changes guard title (S7).
  ///
  /// In ar, this message translates to:
  /// **'تجاهل التغييرات؟'**
  String get accountDiscardTitle;

  /// Unsaved-changes guard message.
  ///
  /// In ar, this message translates to:
  /// **'لم تُحفظ تعديلاتكم بعد.'**
  String get accountDiscardMessage;

  /// Unsaved-changes guard confirm.
  ///
  /// In ar, this message translates to:
  /// **'تجاهل'**
  String get accountDiscardAction;

  /// An empty optional value.
  ///
  /// In ar, this message translates to:
  /// **'غير محدد'**
  String get accountNotSet;

  /// Image sheet (M53): pick from the device.
  ///
  /// In ar, this message translates to:
  /// **'اختيار صورة'**
  String get accountImagePick;

  /// Image sheet (M53): remove the current image.
  ///
  /// In ar, this message translates to:
  /// **'إزالة الصورة'**
  String get accountImageRemove;

  /// M52 title and hub link.
  ///
  /// In ar, this message translates to:
  /// **'الملف الشخصي'**
  String get accountProfileTitle;

  /// M52 e-mail helper.
  ///
  /// In ar, this message translates to:
  /// **'يُستخدم لتسجيل الدخول ولا يمكن تغييره.'**
  String get accountProfileEmailHelper;

  /// M52 avatar button.
  ///
  /// In ar, this message translates to:
  /// **'تغيير الصورة الشخصية'**
  String get accountProfileAvatarChange;

  /// M52 avatar rules.
  ///
  /// In ar, this message translates to:
  /// **'PNG أو JPG أو WEBP بحجم أقصاه 2 ميجابايت.'**
  String get accountProfileAvatarHint;

  /// M52 toast.
  ///
  /// In ar, this message translates to:
  /// **'حُدّثت الصورة الشخصية.'**
  String get accountProfileAvatarUpdated;

  /// M52 toast.
  ///
  /// In ar, this message translates to:
  /// **'أُزيلت الصورة الشخصية.'**
  String get accountProfileAvatarRemoved;

  /// M54 title, hub link and submit.
  ///
  /// In ar, this message translates to:
  /// **'تغيير كلمة المرور'**
  String get accountPasswordTitle;

  /// M54 field.
  ///
  /// In ar, this message translates to:
  /// **'كلمة المرور الحالية'**
  String get accountPasswordCurrent;

  /// M54 field.
  ///
  /// In ar, this message translates to:
  /// **'كلمة المرور الجديدة'**
  String get accountPasswordNew;

  /// M54 field.
  ///
  /// In ar, this message translates to:
  /// **'تأكيد كلمة المرور الجديدة'**
  String get accountPasswordConfirm;

  /// M54 notice (PUT /me/password revokes other tokens).
  ///
  /// In ar, this message translates to:
  /// **'سيُسجَّل خروجكم من الأجهزة الأخرى.'**
  String get accountPasswordOtherDevices;

  /// M54 success toast.
  ///
  /// In ar, this message translates to:
  /// **'تم تغيير كلمة المرور، وسُجّل خروجكم من الأجهزة الأخرى.'**
  String get accountPasswordChanged;

  /// M55 title and hub link.
  ///
  /// In ar, this message translates to:
  /// **'بيانات المنشأة'**
  String get accountOrganizationTitle;

  /// M55 edit action.
  ///
  /// In ar, this message translates to:
  /// **'تعديل البيانات'**
  String get accountOrganizationEdit;

  /// M55 without organization.update.
  ///
  /// In ar, this message translates to:
  /// **'يعدّل مالك الحساب أو مديره بيانات المنشأة.'**
  String get accountOrganizationReadOnly;

  /// M55 section.
  ///
  /// In ar, this message translates to:
  /// **'الهوية'**
  String get accountOrganizationIdentity;

  /// M55 section.
  ///
  /// In ar, this message translates to:
  /// **'الضريبة'**
  String get accountOrganizationTax;

  /// M55 section.
  ///
  /// In ar, this message translates to:
  /// **'الموقع'**
  String get accountOrganizationLocation;

  /// M55 section.
  ///
  /// In ar, this message translates to:
  /// **'التواصل'**
  String get accountOrganizationContact;

  /// M55 section: categories and suggestions.
  ///
  /// In ar, this message translates to:
  /// **'النشاط'**
  String get accountOrganizationActivity;

  /// M55 read-only field.
  ///
  /// In ar, this message translates to:
  /// **'البريد الإلكتروني للمنشأة'**
  String get accountOrganizationEmail;

  /// M55 read-only field.
  ///
  /// In ar, this message translates to:
  /// **'هاتف المنشأة'**
  String get accountOrganizationPhone;

  /// M55 CR helper.
  ///
  /// In ar, this message translates to:
  /// **'لا يمكن تغيير رقم السجل التجاري.'**
  String get accountOrganizationCrReadOnly;

  /// M55 VAT status.
  ///
  /// In ar, this message translates to:
  /// **'مسجّلة'**
  String get accountOrganizationVatRegistered;

  /// M55 VAT status.
  ///
  /// In ar, this message translates to:
  /// **'غير مسجّلة'**
  String get accountOrganizationVatNotRegistered;

  /// M55 suggestions visibility.
  ///
  /// In ar, this message translates to:
  /// **'تظهر في اقتراحات طارحي المنافسات'**
  String get accountOrganizationSuggestionsOn;

  /// M55 suggestions visibility.
  ///
  /// In ar, this message translates to:
  /// **'لا تظهر في اقتراحات طارحي المنافسات'**
  String get accountOrganizationSuggestionsOff;

  /// M55 features section.
  ///
  /// In ar, this message translates to:
  /// **'الخصائص'**
  String get accountOrganizationFeatures;

  /// M55 feature api_enabled.
  ///
  /// In ar, this message translates to:
  /// **'الربط البرمجي (API)'**
  String get accountOrganizationFeatureApi;

  /// M55 feature auction_enabled.
  ///
  /// In ar, this message translates to:
  /// **'المزايدات'**
  String get accountOrganizationFeatureAuction;

  /// M55 feature sponsorship_enabled.
  ///
  /// In ar, this message translates to:
  /// **'تغطية رسوم المشاركة'**
  String get accountOrganizationFeatureSponsorship;

  /// M55 feature state.
  ///
  /// In ar, this message translates to:
  /// **'مفعّلة'**
  String get accountOrganizationFeatureOn;

  /// M55 feature state.
  ///
  /// In ar, this message translates to:
  /// **'غير مفعّلة'**
  String get accountOrganizationFeatureOff;

  /// M55 features note.
  ///
  /// In ar, this message translates to:
  /// **'لتفعيلها تواصلوا مع بافو.'**
  String get accountOrganizationFeaturesNote;

  /// M55 billing-profile banner (no purchase action).
  ///
  /// In ar, this message translates to:
  /// **'بيانات الفوترة غير مكتملة. المطلوب: {fields}.'**
  String accountOrganizationBillingIncomplete(String fields);

  /// M55 banner: a missing field the app has no label for.
  ///
  /// In ar, this message translates to:
  /// **'حقول أخرى'**
  String get accountOrganizationOtherFields;

  /// M55 banner action (opens the edit form).
  ///
  /// In ar, this message translates to:
  /// **'إكمال البيانات'**
  String get accountOrganizationComplete;

  /// M55 logo button.
  ///
  /// In ar, this message translates to:
  /// **'تغيير الشعار'**
  String get accountOrganizationLogoChange;

  /// M55 logo rules.
  ///
  /// In ar, this message translates to:
  /// **'صورة بحجم أقصاه 2 ميجابايت.'**
  String get accountOrganizationLogoHint;

  /// M55 toast.
  ///
  /// In ar, this message translates to:
  /// **'حُدّث الشعار.'**
  String get accountOrganizationLogoUpdated;

  /// M55 toast.
  ///
  /// In ar, this message translates to:
  /// **'أُزيل الشعار.'**
  String get accountOrganizationLogoRemoved;

  /// M55 profile document.
  ///
  /// In ar, this message translates to:
  /// **'ملف تعريف المنشأة'**
  String get accountOrganizationProfileDocument;

  /// M55 without a profile document.
  ///
  /// In ar, this message translates to:
  /// **'لم يُرفع ملف تعريف بعد.'**
  String get accountOrganizationProfileDocumentNone;

  /// M55 note: the PDF upload is web-only.
  ///
  /// In ar, this message translates to:
  /// **'يُرفع ملف التعريف من لوحة تحكم بافو على الويب.'**
  String get accountOrganizationProfileDocumentWeb;

  /// M56 title and hub link.
  ///
  /// In ar, this message translates to:
  /// **'فريق العمل'**
  String get accountTeamTitle;

  /// M56 seats meter label.
  ///
  /// In ar, this message translates to:
  /// **'المقاعد المستخدمة'**
  String get accountTeamSeats;

  /// seat_limit_reached with details.seats (§3.2).
  ///
  /// In ar, this message translates to:
  /// **'اكتملت مقاعد باقتكم ({used}/{total}).'**
  String accountTeamSeatsFull(int used, int total);

  /// M56 action and M57 new title.
  ///
  /// In ar, this message translates to:
  /// **'دعوة عضو'**
  String get accountTeamInvite;

  /// M56 empty state.
  ///
  /// In ar, this message translates to:
  /// **'لم تضيفوا أعضاء بعد'**
  String get accountTeamEmptyTitle;

  /// M56 empty state.
  ///
  /// In ar, this message translates to:
  /// **'ادعوا زملاءكم لإدارة المنافسات والعروض معكم.'**
  String get accountTeamEmptyMessage;

  /// Membership status invited.
  ///
  /// In ar, this message translates to:
  /// **'بانتظار قبول الدعوة'**
  String get accountTeamStatusInvited;

  /// Membership status active.
  ///
  /// In ar, this message translates to:
  /// **'نشط'**
  String get accountTeamStatusActive;

  /// Membership status inactive.
  ///
  /// In ar, this message translates to:
  /// **'موقوف'**
  String get accountTeamStatusInactive;

  /// Membership flag can_award.
  ///
  /// In ar, this message translates to:
  /// **'صلاحية الترسية'**
  String get accountTeamCanAward;

  /// Membership flag can_purchase.
  ///
  /// In ar, this message translates to:
  /// **'صلاحية الشراء'**
  String get accountTeamCanPurchase;

  /// M57 can_award helper.
  ///
  /// In ar, this message translates to:
  /// **'يستطيع ترسية المنافسات.'**
  String get accountTeamCanAwardHint;

  /// M57 can_purchase helper.
  ///
  /// In ar, this message translates to:
  /// **'يستطيع الشراء من لوحة تحكم بافو على الويب.'**
  String get accountTeamCanPurchaseHint;

  /// M56 marker of the signed-in user row.
  ///
  /// In ar, this message translates to:
  /// **'أنت'**
  String get accountTeamYou;

  /// M56 owner or own row (locked).
  ///
  /// In ar, this message translates to:
  /// **'لا يمكن تعديل هذا العضو.'**
  String get accountTeamLocked;

  /// M56 invited date.
  ///
  /// In ar, this message translates to:
  /// **'دُعي في {date}'**
  String accountTeamInvitedOn(String date);

  /// M56 joined date.
  ///
  /// In ar, this message translates to:
  /// **'انضم في {date}'**
  String accountTeamJoinedOn(String date);

  /// M57 edit title.
  ///
  /// In ar, this message translates to:
  /// **'عضو الفريق'**
  String get accountTeamMemberTitle;

  /// M57 role group.
  ///
  /// In ar, this message translates to:
  /// **'الدور'**
  String get accountTeamRole;

  /// M57 admin role helper.
  ///
  /// In ar, this message translates to:
  /// **'يدير بيانات المنشأة والفريق وجميع المنافسات.'**
  String get accountTeamRoleAdminHint;

  /// M57 member role helper.
  ///
  /// In ar, this message translates to:
  /// **'يطرح المنافسات ويدير ما أنشأه منها، ويقدّم العروض.'**
  String get accountTeamRoleMemberHint;

  /// M57 submit (new).
  ///
  /// In ar, this message translates to:
  /// **'إرسال الدعوة'**
  String get accountTeamSendInvite;

  /// M57 toast after an invite.
  ///
  /// In ar, this message translates to:
  /// **'أُرسلت الدعوة إلى {email}.'**
  String accountTeamInviteSent(String email);

  /// M57 action (invited members).
  ///
  /// In ar, this message translates to:
  /// **'إعادة إرسال الدعوة'**
  String get accountTeamResend;

  /// M57 toast.
  ///
  /// In ar, this message translates to:
  /// **'أُعيد إرسال الدعوة.'**
  String get accountTeamResent;

  /// M57 action.
  ///
  /// In ar, this message translates to:
  /// **'إيقاف العضو'**
  String get accountTeamDeactivate;

  /// M57 deactivate confirmation.
  ///
  /// In ar, this message translates to:
  /// **'لن يتمكن العضو من تسجيل الدخول حتى إعادة تفعيله.'**
  String get accountTeamDeactivateConfirmMessage;

  /// M57 toast.
  ///
  /// In ar, this message translates to:
  /// **'أُوقف العضو.'**
  String get accountTeamDeactivated;

  /// M57 action.
  ///
  /// In ar, this message translates to:
  /// **'إعادة تفعيل العضو'**
  String get accountTeamReactivate;

  /// M57 toast.
  ///
  /// In ar, this message translates to:
  /// **'أُعيد تفعيل العضو.'**
  String get accountTeamReactivated;

  /// M57 destructive action.
  ///
  /// In ar, this message translates to:
  /// **'إزالة العضو'**
  String get accountTeamRemove;

  /// M57 remove confirmation.
  ///
  /// In ar, this message translates to:
  /// **'إزالة {name} من الفريق؟'**
  String accountTeamRemoveConfirmTitle(String name);

  /// M57 remove confirmation.
  ///
  /// In ar, this message translates to:
  /// **'سيفقد العضو الوصول إلى حساب المنشأة، ويبقى سجل ما أنجزه.'**
  String get accountTeamRemoveConfirmMessage;

  /// M57 toast.
  ///
  /// In ar, this message translates to:
  /// **'أُزيل العضو من الفريق.'**
  String get accountTeamRemoved;

  /// M57 unknown membership id.
  ///
  /// In ar, this message translates to:
  /// **'هذا العضو غير موجود في فريقكم.'**
  String get accountTeamMemberNotFound;

  /// Invoices list title and hub link.
  ///
  /// In ar, this message translates to:
  /// **'الفواتير'**
  String get accountInvoicesTitle;

  /// Invoices empty state.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد فواتير بعد'**
  String get accountInvoicesEmptyTitle;

  /// Invoice type tax_invoice.
  ///
  /// In ar, this message translates to:
  /// **'فاتورة ضريبية'**
  String get accountInvoicesTypeTax;

  /// Invoice type credit_note.
  ///
  /// In ar, this message translates to:
  /// **'إشعار دائن'**
  String get accountInvoicesTypeCredit;

  /// E-invoice status cleared.
  ///
  /// In ar, this message translates to:
  /// **'معتمدة'**
  String get accountInvoicesStatusCleared;

  /// E-invoice status reported.
  ///
  /// In ar, this message translates to:
  /// **'مُبلَّغ عنها'**
  String get accountInvoicesStatusReported;

  /// E-invoice status pending.
  ///
  /// In ar, this message translates to:
  /// **'قيد الإصدار'**
  String get accountInvoicesStatusPending;

  /// E-invoice status rejected.
  ///
  /// In ar, this message translates to:
  /// **'مرفوضة'**
  String get accountInvoicesStatusRejected;

  /// E-invoice status failed.
  ///
  /// In ar, this message translates to:
  /// **'تعذّر الإصدار'**
  String get accountInvoicesStatusFailed;

  /// Invoice issue date.
  ///
  /// In ar, this message translates to:
  /// **'صدرت في {date}'**
  String accountInvoicesIssued(String date);

  /// M59 title and hub link.
  ///
  /// In ar, this message translates to:
  /// **'الإعدادات'**
  String get accountSettingsTitle;

  /// M59 section.
  ///
  /// In ar, this message translates to:
  /// **'حول التطبيق'**
  String get accountSettingsAbout;

  /// M59 row.
  ///
  /// In ar, this message translates to:
  /// **'إصدار التطبيق'**
  String get accountSettingsVersion;

  /// M59 section.
  ///
  /// In ar, this message translates to:
  /// **'الوثائق القانونية'**
  String get accountSettingsLegal;

  /// M59 row.
  ///
  /// In ar, this message translates to:
  /// **'تراخيص البرمجيات مفتوحة المصدر'**
  String get accountSettingsLicenses;

  /// M60 title and hub link.
  ///
  /// In ar, this message translates to:
  /// **'المساعدة والتواصل'**
  String get accountHelpTitle;

  /// M60 intro.
  ///
  /// In ar, this message translates to:
  /// **'نسعد بمساعدتكم. تواصلوا معنا مباشرة أو أرسلوا رسالة.'**
  String get accountHelpIntro;

  /// M60 when app-config has no support contacts.
  ///
  /// In ar, this message translates to:
  /// **'بيانات التواصل غير متاحة حالياً، ويمكنكم مراسلتنا عبر النموذج.'**
  String get accountHelpNoContacts;

  /// M60 form title.
  ///
  /// In ar, this message translates to:
  /// **'راسلونا'**
  String get accountHelpFormTitle;

  /// M60 field.
  ///
  /// In ar, this message translates to:
  /// **'الموضوع'**
  String get accountHelpSubject;

  /// M60 field.
  ///
  /// In ar, this message translates to:
  /// **'الرسالة'**
  String get accountHelpMessage;

  /// M60 submit.
  ///
  /// In ar, this message translates to:
  /// **'إرسال'**
  String get accountHelpSend;

  /// M60 success.
  ///
  /// In ar, this message translates to:
  /// **'استلمنا رسالتكم'**
  String get accountHelpSentTitle;

  /// M60 success.
  ///
  /// In ar, this message translates to:
  /// **'سيتواصل معكم فريق بافو قريباً عبر البريد الإلكتروني.'**
  String get accountHelpSentMessage;

  /// M60 success action.
  ///
  /// In ar, this message translates to:
  /// **'إرسال رسالة أخرى'**
  String get accountHelpSendAnother;

  /// M61 title, hub link and destructive button.
  ///
  /// In ar, this message translates to:
  /// **'حذف الحساب'**
  String get accountDeleteTitle;

  /// M61 scope for the owner.
  ///
  /// In ar, this message translates to:
  /// **'سيُحذف حساب المنشأة وجميع أعضائها بعد 14 يوماً.'**
  String get accountDeleteScopeOrganization;

  /// M61 scope for other users.
  ///
  /// In ar, this message translates to:
  /// **'سيُحذف حسابكم الشخصي فقط بعد 14 يوماً، وتبقى المنشأة وبقية أعضائها.'**
  String get accountDeleteScopeUser;

  /// M61 note.
  ///
  /// In ar, this message translates to:
  /// **'يمكنكم إلغاء الطلب خلال هذه المدة.'**
  String get accountDeleteCancelWindow;

  /// M61 kept-records title.
  ///
  /// In ar, this message translates to:
  /// **'ما الذي يبقى محفوظاً'**
  String get accountDeleteKeptTitle;

  /// M61 kept records.
  ///
  /// In ar, this message translates to:
  /// **'نحتفظ بالسجلات النظامية والمالية: الفواتير والمدفوعات والمنافسات وسجل العروض.'**
  String get accountDeleteKept;

  /// M61 optional reason.
  ///
  /// In ar, this message translates to:
  /// **'سبب الحذف'**
  String get accountDeleteReason;

  /// M61 confirmation title.
  ///
  /// In ar, this message translates to:
  /// **'حذف الحساب؟'**
  String get accountDeleteConfirmTitle;

  /// M61 pending request.
  ///
  /// In ar, this message translates to:
  /// **'سيُحذف الحساب في {date}.'**
  String accountDeletePending(String date);

  /// M61 cancel action.
  ///
  /// In ar, this message translates to:
  /// **'إلغاء طلب الحذف'**
  String get accountDeleteCancel;

  /// M61 toast.
  ///
  /// In ar, this message translates to:
  /// **'أُلغي طلب الحذف.'**
  String get accountDeleteCancelled;

  /// M61 toast.
  ///
  /// In ar, this message translates to:
  /// **'سُجّل طلب حذف الحساب.'**
  String get accountDeleteRequested;

  /// M61 account_deletion_blocked.
  ///
  /// In ar, this message translates to:
  /// **'لا يمكن حذف الحساب الآن'**
  String get accountDeleteBlockedTitle;

  /// M61 account_deletion_blocked.
  ///
  /// In ar, this message translates to:
  /// **'أنهوا المنافسات والمشاركات المفتوحة التالية أولاً.'**
  String get accountDeleteBlockedMessage;

  /// M61 blocker type issued_competition.
  ///
  /// In ar, this message translates to:
  /// **'منافسة تطرحونها'**
  String get accountDeleteBlockerIssued;

  /// M61 blocker type participation.
  ///
  /// In ar, this message translates to:
  /// **'مشاركة في منافسة'**
  String get accountDeleteBlockerParticipation;

  /// M16 status group segment: scheduled, live, evaluation, BAFO round.
  ///
  /// In ar, this message translates to:
  /// **'النشطة'**
  String get competitionsParticipatingFilterActive;

  /// M16 status group segment: awarded, closed without award, cancelled.
  ///
  /// In ar, this message translates to:
  /// **'المنتهية'**
  String get competitionsParticipatingFilterEnded;

  /// M16 status group segment: every competition.
  ///
  /// In ar, this message translates to:
  /// **'الكل'**
  String get competitionsParticipatingFilterAll;

  /// M16 direction filter: tenders and auctions.
  ///
  /// In ar, this message translates to:
  /// **'كل الأنواع'**
  String get competitionsParticipatingDirectionAll;

  /// M16 search field hint.
  ///
  /// In ar, this message translates to:
  /// **'ابحث في عناوين المنافسات'**
  String get competitionsParticipatingSearchHint;

  /// M16 empty state when a search or filter matches nothing.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد نتائج'**
  String get competitionsParticipatingNoResultsTitle;

  /// M16 empty state body for a narrowed list.
  ///
  /// In ar, this message translates to:
  /// **'جرّب كلمات بحث أو تصفية أخرى.'**
  String get competitionsParticipatingNoResultsMessage;

  /// M16 notice: loaded invitations that need a join or decline.
  ///
  /// In ar, this message translates to:
  /// **'{count, plural, =1{دعوة واحدة بانتظار ردّكم} =2{دعوتان بانتظار ردّكم} few{{count} دعوات بانتظار ردّكم} many{{count} دعوة بانتظار ردّكم} other{{count} دعوة بانتظار ردّكم}}'**
  String competitionsParticipatingNeedsAction(int count);

  /// M16 card: the organisation's current offer (formatted amount).
  ///
  /// In ar, this message translates to:
  /// **'عرضك: {amount}'**
  String competitionsParticipatingMyOffer(String amount);

  /// M16 infinite scroll: the next page failed.
  ///
  /// In ar, this message translates to:
  /// **'تعذّر تحميل المزيد.'**
  String get competitionsParticipatingLoadMoreFailed;

  /// M16 card semantics label: title and status.
  ///
  /// In ar, this message translates to:
  /// **'{title}، {status}'**
  String competitionsParticipatingCardSemantics(String title, String status);

  /// M16/M17 button: open the join sheet.
  ///
  /// In ar, this message translates to:
  /// **'الانضمام'**
  String get invitationsJoinAction;

  /// M16/M17 button: open the decline sheet.
  ///
  /// In ar, this message translates to:
  /// **'الاعتذار'**
  String get invitationsDeclineAction;

  /// M17 access card title.
  ///
  /// In ar, this message translates to:
  /// **'دعوة للمشاركة'**
  String get invitationsCardTitle;

  /// M17 teaser line with the issuer name.
  ///
  /// In ar, this message translates to:
  /// **'دعاكم {issuer} للمشاركة في هذه المنافسة.'**
  String invitationsInvitedBy(String issuer);

  /// M17 teaser: when the invitation was sent.
  ///
  /// In ar, this message translates to:
  /// **'أُرسلت الدعوة في {date}'**
  String invitationsSentAt(String date);

  /// M17/M18 access: join_required with own_plan.
  ///
  /// In ar, this message translates to:
  /// **'تنضمون بباقتكم الحالية.'**
  String get invitationsOwnPlan;

  /// M17/M18 access: join_required with a sponsored pass.
  ///
  /// In ar, this message translates to:
  /// **'تغطي {sponsor} رسوم مشاركتكم في هذه المنافسة فقط.'**
  String invitationsSponsoredOnly(String sponsor);

  /// M17 access: plan_required (information only, no purchase).
  ///
  /// In ar, this message translates to:
  /// **'تحتاجون إلى باقة فعّالة للانضمام إلى هذه المنافسة.'**
  String get invitationsPlanRequiredBody;

  /// M17 access: opens the access info sheet (M20).
  ///
  /// In ar, this message translates to:
  /// **'لماذا؟'**
  String get invitationsPlanRequiredMore;

  /// M17 access unavailable: declined.
  ///
  /// In ar, this message translates to:
  /// **'اعتذرتم عن المشاركة في هذه المنافسة.'**
  String get invitationsUnavailableDeclined;

  /// M17 access unavailable: expired.
  ///
  /// In ar, this message translates to:
  /// **'انتهت صلاحية هذه الدعوة.'**
  String get invitationsUnavailableExpired;

  /// M17 access unavailable: revoked.
  ///
  /// In ar, this message translates to:
  /// **'ألغى طارح المنافسة هذه الدعوة.'**
  String get invitationsUnavailableRevoked;

  /// M17 access unavailable: after the join deadline.
  ///
  /// In ar, this message translates to:
  /// **'انتهى موعد الانضمام إلى هذه المنافسة.'**
  String get invitationsUnavailableDeadline;

  /// M17 access unavailable: competition status.
  ///
  /// In ar, this message translates to:
  /// **'لم تعد هذه المنافسة تقبل متنافسين جدداً.'**
  String get invitationsUnavailableClosed;

  /// M17 section: invitation documents.
  ///
  /// In ar, this message translates to:
  /// **'مستندات الدعوة'**
  String get invitationsDocumentsTitle;

  /// M18 sheet title.
  ///
  /// In ar, this message translates to:
  /// **'الانضمام إلى المنافسة'**
  String get invitationsJoinTitle;

  /// M18 sheet intro.
  ///
  /// In ar, this message translates to:
  /// **'راجعوا قواعد المنافسة قبل الانضمام. بعد الانضمام تصبح منشأتكم متنافساً ويمكنكم تقديم العروض.'**
  String get invitationsJoinIntro;

  /// M18 required checkbox.
  ///
  /// In ar, this message translates to:
  /// **'أوافق على شروط المنافسة'**
  String get invitationsJoinAcceptTerms;

  /// M18 checkbox error.
  ///
  /// In ar, this message translates to:
  /// **'وافقوا على شروط المنافسة للمتابعة.'**
  String get invitationsJoinTermsRequired;

  /// M18 confirm button.
  ///
  /// In ar, this message translates to:
  /// **'تأكيد الانضمام'**
  String get invitationsJoinConfirm;

  /// M18 success toast.
  ///
  /// In ar, this message translates to:
  /// **'انضممتم إلى المنافسة'**
  String get invitationsJoinSucceeded;

  /// M19 sheet title.
  ///
  /// In ar, this message translates to:
  /// **'الاعتذار عن المشاركة'**
  String get invitationsDeclineTitle;

  /// M19 sheet body.
  ///
  /// In ar, this message translates to:
  /// **'لن تتمكنوا من الانضمام إلى هذه المنافسة بعد الاعتذار.'**
  String get invitationsDeclineMessage;

  /// M19 optional reason field label.
  ///
  /// In ar, this message translates to:
  /// **'سبب الاعتذار'**
  String get invitationsDeclineReasonLabel;

  /// M19 reason helper text.
  ///
  /// In ar, this message translates to:
  /// **'اختياري، ويطّلع عليه طارح المنافسة.'**
  String get invitationsDeclineReasonHelper;

  /// M19 destructive confirm button (names the action).
  ///
  /// In ar, this message translates to:
  /// **'تأكيد الاعتذار'**
  String get invitationsDeclineConfirm;

  /// M19 success toast.
  ///
  /// In ar, this message translates to:
  /// **'أُرسل اعتذاركم إلى طارح المنافسة.'**
  String get invitationsDeclineSucceeded;

  /// M20 access info sheet title (plan_required).
  ///
  /// In ar, this message translates to:
  /// **'الانضمام يتطلب باقة فعّالة'**
  String get invitationsAccessInfoTitle;

  /// M20 access info body (plan_required).
  ///
  /// In ar, this message translates to:
  /// **'تحتاج منشأتكم إلى باقة فعّالة للانضمام.'**
  String get invitationsAccessInfoPlanRequired;

  /// M20 access info sheet title (unavailable).
  ///
  /// In ar, this message translates to:
  /// **'الانضمام غير متاح'**
  String get invitationsAccessInfoUnavailableTitle;

  /// M20: decline stays available.
  ///
  /// In ar, this message translates to:
  /// **'يمكنكم الاعتذار عن الدعوة إن لم ترغبوا في المشاركة.'**
  String get invitationsAccessInfoDeclineHint;

  /// M21 link to the live room.
  ///
  /// In ar, this message translates to:
  /// **'غرفة العروض'**
  String get competitionsParticipantLiveRoom;

  /// M21 link to Q&A.
  ///
  /// In ar, this message translates to:
  /// **'الاستفسارات'**
  String get competitionsParticipantQa;

  /// M21 link to my offers.
  ///
  /// In ar, this message translates to:
  /// **'عروضي'**
  String get competitionsParticipantMyOffers;

  /// M21 standing summary title.
  ///
  /// In ar, this message translates to:
  /// **'موقف عرضكم'**
  String get competitionsParticipantStandingTitle;

  /// M21 standing summary: current offer label.
  ///
  /// In ar, this message translates to:
  /// **'عرضكم الحالي'**
  String get competitionsParticipantCurrentOffer;

  /// M21: the organisation's own alias.
  ///
  /// In ar, this message translates to:
  /// **'رقمكم في هذه المنافسة: المتنافس {alias}'**
  String competitionsParticipantAlias(int alias);

  /// M21 issuer card: verified badge.
  ///
  /// In ar, this message translates to:
  /// **'منشأة موثّقة'**
  String get competitionsParticipantVerified;

  /// M21: status closed.
  ///
  /// In ar, this message translates to:
  /// **'أُغلق استقبال العروض، والمنافسة الآن قيد التقييم.'**
  String get competitionsParticipantEvaluation;

  /// M21: status scheduled.
  ///
  /// In ar, this message translates to:
  /// **'تبدأ العروض عند موعد البدء، ويمكنكم متابعة الاستفسارات حتى ذلك الحين.'**
  String get competitionsParticipantScheduled;

  /// M29 result panel title.
  ///
  /// In ar, this message translates to:
  /// **'النتيجة'**
  String get competitionsParticipantResultTitle;

  /// M29: won.
  ///
  /// In ar, this message translates to:
  /// **'تمت ترسية هذه المنافسة عليكم.'**
  String get competitionsParticipantResultWon;

  /// M29: winning amount when published.
  ///
  /// In ar, this message translates to:
  /// **'قيمة العرض الفائز: {amount}'**
  String competitionsParticipantResultWinningAmount(String amount);

  /// M29: not selected.
  ///
  /// In ar, this message translates to:
  /// **'أُرسيت المنافسة على متنافس آخر.'**
  String get competitionsParticipantResultNotSelected;

  /// M29: not awarded.
  ///
  /// In ar, this message translates to:
  /// **'أُغلقت المنافسة دون ترسية.'**
  String get competitionsParticipantResultNotAwarded;

  /// M29: result_publication none.
  ///
  /// In ar, this message translates to:
  /// **'لا تنشر هذه المنافسة نتائجها للمتنافسين.'**
  String get competitionsParticipantResultHidden;

  /// M29: message to the winner.
  ///
  /// In ar, this message translates to:
  /// **'رسالة طارح المنافسة'**
  String get competitionsParticipantResultMessage;

  /// M23 app bar title.
  ///
  /// In ar, this message translates to:
  /// **'غرفة العروض'**
  String get liveRoomTitle;

  /// M23: leading amount when show_prices.
  ///
  /// In ar, this message translates to:
  /// **'العرض المتصدر: {amount}'**
  String liveLeadingAmount(String amount);

  /// M23 anti-sniping banner (auto extension, whole minutes).
  ///
  /// In ar, this message translates to:
  /// **'{minutes, plural, =1{مُدّد وقت الإغلاق دقيقة واحدة بسبب عرض في الدقائق الأخيرة.} =2{مُدّد وقت الإغلاق دقيقتين بسبب عرض في الدقائق الأخيرة.} few{مُدّد وقت الإغلاق {minutes} دقائق بسبب عرض في الدقائق الأخيرة.} many{مُدّد وقت الإغلاق {minutes} دقيقة بسبب عرض في الدقائق الأخيرة.} other{مُدّد وقت الإغلاق {minutes} دقيقة بسبب عرض في الدقائق الأخيرة.}}'**
  String liveExtended(int minutes);

  /// M23 anti-sniping banner (auto extension, seconds).
  ///
  /// In ar, this message translates to:
  /// **'{seconds, plural, =1{مُدّد وقت الإغلاق ثانية واحدة بسبب عرض في الدقائق الأخيرة.} =2{مُدّد وقت الإغلاق ثانيتين بسبب عرض في الدقائق الأخيرة.} few{مُدّد وقت الإغلاق {seconds} ثوانٍ بسبب عرض في الدقائق الأخيرة.} many{مُدّد وقت الإغلاق {seconds} ثانية بسبب عرض في الدقائق الأخيرة.} other{مُدّد وقت الإغلاق {seconds} ثانية بسبب عرض في الدقائق الأخيرة.}}'**
  String liveExtendedSeconds(int seconds);

  /// M23 anti-sniping banner without a known duration.
  ///
  /// In ar, this message translates to:
  /// **'مُدّد وقت الإغلاق بسبب عرض في الدقائق الأخيرة.'**
  String get liveExtendedGeneric;

  /// M23 banner: a manual or admin extension.
  ///
  /// In ar, this message translates to:
  /// **'مدّد طارح المنافسة موعد الإغلاق.'**
  String get liveExtendedManual;

  /// M23 hint in the last 10 seconds.
  ///
  /// In ar, this message translates to:
  /// **'يُعتمد وقت استلام العرض على خادم بافو.'**
  String get liveHintServerTiming;

  /// M23 hint when a clock round trip exceeds 2 s.
  ///
  /// In ar, this message translates to:
  /// **'الاتصال بطيء، فقدّم عرضك مبكراً.'**
  String get liveHintSlowConnection;

  /// M23 connection indicator: connecting.
  ///
  /// In ar, this message translates to:
  /// **'جارٍ الاتصال…'**
  String get liveConnectionConnecting;

  /// M23 connection indicator: connected.
  ///
  /// In ar, this message translates to:
  /// **'مباشر'**
  String get liveConnectionLive;

  /// M23 connection banner: reconnecting (submit disabled).
  ///
  /// In ar, this message translates to:
  /// **'جارٍ إعادة الاتصال…'**
  String get liveConnectionReconnecting;

  /// M23 connection banner: polling fallback.
  ///
  /// In ar, this message translates to:
  /// **'{seconds, plural, =1{التحديثات المباشرة متأخرة، ونحدّث كل ثانية.} =2{التحديثات المباشرة متأخرة، ونحدّث كل ثانيتين.} few{التحديثات المباشرة متأخرة، ونحدّث كل {seconds} ثوانٍ.} many{التحديثات المباشرة متأخرة، ونحدّث كل {seconds} ثانية.} other{التحديثات المباشرة متأخرة، ونحدّث كل {seconds} ثانية.}}'**
  String liveConnectionPolling(int seconds);

  /// M23 connection banner: offline.
  ///
  /// In ar, this message translates to:
  /// **'لا يوجد اتصال بالإنترنت.'**
  String get liveConnectionOffline;

  /// M23: why the composer is disabled.
  ///
  /// In ar, this message translates to:
  /// **'يتوقف تقديم العروض حتى يعود الاتصال.'**
  String get liveConnectionSubmitPaused;

  /// M23: initial phase note.
  ///
  /// In ar, this message translates to:
  /// **'قبل فترة التسعير النهائية يظهر عرضك فقط.'**
  String get liveInitialPhaseNote;

  /// M23 ladder title (only when the competition shows it).
  ///
  /// In ar, this message translates to:
  /// **'ترتيب العروض'**
  String get liveLadderTitle;

  /// M23 ladder row: another participant by alias.
  ///
  /// In ar, this message translates to:
  /// **'المتنافس {alias}'**
  String liveLadderParticipant(int alias);

  /// M23 ladder row: the viewer.
  ///
  /// In ar, this message translates to:
  /// **'أنتم'**
  String get liveLadderYou;

  /// M23 ladder row semantics.
  ///
  /// In ar, this message translates to:
  /// **'المرتبة {rank}، {name}، {amount}'**
  String liveLadderRowSemantics(int rank, String name, String amount);

  /// M23 my offer card title.
  ///
  /// In ar, this message translates to:
  /// **'عرضك الحالي'**
  String get liveMyOfferTitle;

  /// M23 my offer card: accepted_at with milliseconds, Riyadh time.
  ///
  /// In ar, this message translates to:
  /// **'استُلم في {time}'**
  String liveMyOfferReceivedAt(String time);

  /// M23 my offer card: offers count.
  ///
  /// In ar, this message translates to:
  /// **'{count, plural, =1{عرض واحد مقدّم} =2{عرضان مقدّمان} few{{count} عروض مقدّمة} many{{count} عرضاً مقدّماً} other{{count} عرض مقدّم}}'**
  String liveMyOfferCount(int count);

  /// M23 link to my offers (M28).
  ///
  /// In ar, this message translates to:
  /// **'سجل عروضي'**
  String get liveMyOffersLink;

  /// M23 composer amount field label.
  ///
  /// In ar, this message translates to:
  /// **'مبلغ عرضك'**
  String get liveComposerLabel;

  /// M23 composer button: first offer.
  ///
  /// In ar, this message translates to:
  /// **'تقديم العرض'**
  String get liveComposerSubmitFirst;

  /// M23 composer button: a new offer after one exists (live).
  ///
  /// In ar, this message translates to:
  /// **'تحسين العرض'**
  String get liveComposerSubmitImprove;

  /// M23 composer button: sealed revision.
  ///
  /// In ar, this message translates to:
  /// **'تعديل العرض'**
  String get liveComposerSubmitRevise;

  /// M23 composer button: BAFO round.
  ///
  /// In ar, this message translates to:
  /// **'تقديم العرض النهائي'**
  String get liveComposerSubmitBafo;

  /// M23 chip: fill the required next amount.
  ///
  /// In ar, this message translates to:
  /// **'استخدم {amount}'**
  String liveComposerUseAmount(String amount);

  /// M23 granularity hint (100).
  ///
  /// In ar, this message translates to:
  /// **'بالريال دون هللات'**
  String get liveComposerWholeRiyals;

  /// M23 granularity hint (1).
  ///
  /// In ar, this message translates to:
  /// **'يمكن إدخال الهللات (رقمان عشريان)'**
  String get liveComposerDecimalsAllowed;

  /// M23 composer before opening (live countdown).
  ///
  /// In ar, this message translates to:
  /// **'تبدأ العروض خلال {time}'**
  String liveComposerOpensIn(String time);

  /// M23 after offer_not_accepting with opens_at.
  ///
  /// In ar, this message translates to:
  /// **'تبدأ العروض في {time}'**
  String liveComposerOpensAt(String time);

  /// M23 composer after the close.
  ///
  /// In ar, this message translates to:
  /// **'أُغلق استقبال العروض.'**
  String get liveComposerClosed;

  /// M27: not shortlisted.
  ///
  /// In ar, this message translates to:
  /// **'يجري طارح المنافسة جولة عرض نهائي مع قائمة مختصرة، ويبقى عرضكم الأخير قائماً.'**
  String get liveComposerNotShortlisted;

  /// M26 sealed lock panel title.
  ///
  /// In ar, this message translates to:
  /// **'عروض بظرف مغلق'**
  String get liveSealedTitle;

  /// M26 sealed lock panel.
  ///
  /// In ar, this message translates to:
  /// **'عرضكم مغلق، وتُفتح العروض عند الإغلاق.'**
  String get liveSealedBody;

  /// M26 sealed panel before a first offer.
  ///
  /// In ar, this message translates to:
  /// **'قدّموا عرضكم المغلق قبل الإغلاق، ويمكنكم تعديله حتى ذلك الحين.'**
  String get liveSealedNoOffer;

  /// M23: scheduled competition.
  ///
  /// In ar, this message translates to:
  /// **'لم تبدأ العروض بعد.'**
  String get liveNotLiveYet;

  /// offers.hint.required_next (direction variant).
  ///
  /// In ar, this message translates to:
  /// **'{direction, select, tender{يجب ألا يزيد عرضك التالي عن {amount}.} auction{يجب ألا يقل عرضك التالي عن {amount}.} other{الحد التالي لعرضك: {amount}.}}'**
  String offersHintRequiredNext(String direction, String amount);

  /// offers.hint.start_price (direction variant).
  ///
  /// In ar, this message translates to:
  /// **'{direction, select, tender{لا يتجاوز سعر السقف {amount}.} auction{لا يقل عن سعر الافتتاح {amount}.} other{سعر البداية: {amount}.}}'**
  String offersHintStartPrice(String direction, String amount);

  /// offers.error.start_price (direction variant).
  ///
  /// In ar, this message translates to:
  /// **'{direction, select, tender{لا يمكن أن يتجاوز عرضك سعر السقف {amount}.} auction{لا يمكن أن يقل عرضك عن سعر الافتتاح {amount}.} other{العرض لا يستوفي سعر البداية {amount}.}}'**
  String offersErrorStartPrice(String direction, String amount);

  /// offers.error.step_not_met (direction variant).
  ///
  /// In ar, this message translates to:
  /// **'{direction, select, tender{يجب ألا يزيد عرضك عن {amount}.} auction{يجب ألا يقل عرضك عن {amount}.} other{العرض لا يستوفي الحد الأدنى للتحسين ({amount}).}}'**
  String offersErrorStepNotMet(String direction, String amount);

  /// offers.error.granularity with details.granularity_minor.
  ///
  /// In ar, this message translates to:
  /// **'يجب أن يكون المبلغ من مضاعفات {amount}.'**
  String offersErrorGranularity(String amount);

  /// offers.error.amount_too_large with details.max_amount_minor.
  ///
  /// In ar, this message translates to:
  /// **'يجب ألا يزيد المبلغ عن {amount}.'**
  String offersErrorAmountTooLarge(String amount);

  /// offers.error.bafo_reference (direction variant).
  ///
  /// In ar, this message translates to:
  /// **'{direction, select, tender{لا يمكن أن يزيد عرضك النهائي عن عرضك الأخير {amount}.} auction{لا يمكن أن يقل عرضك النهائي عن عرضك الأخير {amount}.} other{لا يمكن أن يكون عرضك النهائي أسوأ من عرضك الأخير {amount}.}}'**
  String offersErrorBafoReference(String direction, String amount);

  /// Inline error action: fill the required amount.
  ///
  /// In ar, this message translates to:
  /// **'استخدم هذا المبلغ'**
  String get offersUseThisAmount;

  /// M24 confirm sheet title.
  ///
  /// In ar, this message translates to:
  /// **'تأكيد العرض'**
  String get offersConfirmTitle;

  /// M24/M27 confirm sheet title in a BAFO round.
  ///
  /// In ar, this message translates to:
  /// **'تأكيد العرض النهائي'**
  String get offersConfirmBafoTitle;

  /// M24 under the amount.
  ///
  /// In ar, this message translates to:
  /// **'لا يشمل ضريبة القيمة المضافة'**
  String get offersConfirmExclVat;

  /// M24 sealed note.
  ///
  /// In ar, this message translates to:
  /// **'عرضك مغلق ولا يراه غيرك، ويمكنك تعديله حتى الإغلاق.'**
  String get offersConfirmSealed;

  /// M24/M27 BAFO note.
  ///
  /// In ar, this message translates to:
  /// **'هذا عرضك النهائي الوحيد ولا يمكن تعديله.'**
  String get offersConfirmBafo;

  /// M24 confirm button.
  ///
  /// In ar, this message translates to:
  /// **'تأكيد وإرسال'**
  String get offersConfirmAction;

  /// M25 outlier dialog title.
  ///
  /// In ar, this message translates to:
  /// **'تحقق من المبلغ'**
  String get offersConfirmOutlierTitle;

  /// offers.confirm.outlier.lower.
  ///
  /// In ar, this message translates to:
  /// **'هذا العرض أقل من عرضك الحالي بنسبة {pct}.'**
  String offersConfirmOutlierLower(String pct);

  /// offers.confirm.outlier.higher.
  ///
  /// In ar, this message translates to:
  /// **'هذا العرض أعلى من عرضك الحالي بنسبة {pct}.'**
  String offersConfirmOutlierHigher(String pct);

  /// M25 confirm button.
  ///
  /// In ar, this message translates to:
  /// **'إرسال العرض بهذا المبلغ'**
  String get offersConfirmOutlierSend;

  /// M25 cancel button.
  ///
  /// In ar, this message translates to:
  /// **'تعديل المبلغ'**
  String get offersConfirmOutlierEdit;

  /// offers.submitted toast (accepted_at, HH:mm:ss.SSS Riyadh).
  ///
  /// In ar, this message translates to:
  /// **'استُلم عرضك في {time}'**
  String offersSubmitted(String time);

  /// M24 after a network failure (same key on retry).
  ///
  /// In ar, this message translates to:
  /// **'لم نتأكد من استلام عرضك. أعد المحاولة.'**
  String get offersUnconfirmed;

  /// M24 after idempotency_key_reused.
  ///
  /// In ar, this message translates to:
  /// **'تعذّر تأكيد العرض. راجع المبلغ وأكّده من جديد.'**
  String get offersKeyReused;

  /// M23 after too_many_requests.
  ///
  /// In ar, this message translates to:
  /// **'انتظر لحظات قبل تقديم عرض جديد.'**
  String get offersRateLimited;

  /// M26 receipt sheet title.
  ///
  /// In ar, this message translates to:
  /// **'استُلم عرضكم المغلق'**
  String get offersSealedReceived;

  /// M26 receipt: offer seq.
  ///
  /// In ar, this message translates to:
  /// **'رقم الاستلام'**
  String get offersReceiptSeq;

  /// M26 receipt: accepted_at.
  ///
  /// In ar, this message translates to:
  /// **'وقت الاستلام'**
  String get offersReceiptTime;

  /// M26 receipt: amount.
  ///
  /// In ar, this message translates to:
  /// **'المبلغ'**
  String get offersReceiptAmount;

  /// offers.stage.sealed.
  ///
  /// In ar, this message translates to:
  /// **'مغلق'**
  String get offersStageSealed;

  /// offers.stage.initial.
  ///
  /// In ar, this message translates to:
  /// **'أولي'**
  String get offersStageInitial;

  /// offers.stage.live.
  ///
  /// In ar, this message translates to:
  /// **'مباشر'**
  String get offersStageLive;

  /// offers.stage.bafo.
  ///
  /// In ar, this message translates to:
  /// **'نهائي'**
  String get offersStageBafo;

  /// M28 voided badge.
  ///
  /// In ar, this message translates to:
  /// **'ملغى'**
  String get offersVoided;

  /// M28 empty title.
  ///
  /// In ar, this message translates to:
  /// **'لم تقدّموا عروضاً بعد'**
  String get offersMyEmptyTitle;

  /// M28 empty body.
  ///
  /// In ar, this message translates to:
  /// **'قدّموا عرضكم من غرفة العروض.'**
  String get offersMyEmptyMessage;

  /// M28 row: the offer sequence number.
  ///
  /// In ar, this message translates to:
  /// **'العرض رقم {seq}'**
  String offersMySeq(int seq);

  /// M28 row: neutral change (semantics).
  ///
  /// In ar, this message translates to:
  /// **'أقل من عرضكم السابق بنسبة {pct}'**
  String offersMyChangeDown(String pct);

  /// M28 row: neutral change (semantics).
  ///
  /// In ar, this message translates to:
  /// **'أعلى من عرضكم السابق بنسبة {pct}'**
  String offersMyChangeUp(String pct);

  /// M27 BAFO banner.
  ///
  /// In ar, this message translates to:
  /// **'أنتم مدعوون لتقديم أفضل وآخر عرض قبل {cutoff}.'**
  String bafoInvite(String cutoff);

  /// M27 BAFO reference amount.
  ///
  /// In ar, this message translates to:
  /// **'عرضكم الأخير: {amount}'**
  String bafoReferenceAmount(String amount);

  /// bafo.rule (direction variant).
  ///
  /// In ar, this message translates to:
  /// **'{direction, select, tender{لا يمكن أن يكون عرضك النهائي أعلى من عرضك الأخير.} auction{لا يمكن أن يكون عرضك النهائي أقل من عرضك الأخير.} other{لا يمكن أن يكون عرضك النهائي أسوأ من عرضك الأخير.}}'**
  String bafoRule(String direction);

  /// M27 after the BAFO offer.
  ///
  /// In ar, this message translates to:
  /// **'استُلم عرضكم النهائي.'**
  String get bafoSubmitted;

  /// M31 title.
  ///
  /// In ar, this message translates to:
  /// **'الاستفسارات'**
  String get qaTitle;

  /// M31 participant composer action.
  ///
  /// In ar, this message translates to:
  /// **'اطرح سؤالاً'**
  String get qaAskAction;

  /// M31 issuer composer action.
  ///
  /// In ar, this message translates to:
  /// **'انشر إعلاناً لجميع المتنافسين'**
  String get qaAnnounceAction;

  /// M31 reply button.
  ///
  /// In ar, this message translates to:
  /// **'ردّ'**
  String get qaReplyAction;

  /// M32 reply sheet title.
  ///
  /// In ar, this message translates to:
  /// **'الرد على الاستفسار'**
  String get qaReplyTitle;

  /// M32 question field label.
  ///
  /// In ar, this message translates to:
  /// **'نص السؤال'**
  String get qaQuestionLabel;

  /// M32 announcement field label.
  ///
  /// In ar, this message translates to:
  /// **'نص الإعلان'**
  String get qaAnnouncementLabel;

  /// M32 reply field label.
  ///
  /// In ar, this message translates to:
  /// **'نص الرد'**
  String get qaReplyLabel;

  /// M32 helper: participants see aliases only.
  ///
  /// In ar, this message translates to:
  /// **'يرى جميع المتنافسين السؤال دون اسم منشأتكم.'**
  String get qaQuestionHelper;

  /// M32 send button.
  ///
  /// In ar, this message translates to:
  /// **'إرسال'**
  String get qaSend;

  /// M32 success toast (question).
  ///
  /// In ar, this message translates to:
  /// **'نُشر الاستفسار.'**
  String get qaPosted;

  /// M32 success toast (announcement).
  ///
  /// In ar, this message translates to:
  /// **'نُشر الإعلان.'**
  String get qaAnnouncementPosted;

  /// M32 success toast (reply).
  ///
  /// In ar, this message translates to:
  /// **'نُشر الرد.'**
  String get qaReplyPosted;

  /// M31 read-only notice title.
  ///
  /// In ar, this message translates to:
  /// **'أُغلقت الاستفسارات'**
  String get qaClosedTitle;

  /// M31 read-only notice body.
  ///
  /// In ar, this message translates to:
  /// **'لا يمكن نشر استفسارات جديدة في حالة المنافسة الحالية.'**
  String get qaClosedBody;

  /// qa.list.empty.title.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد استفسارات بعد'**
  String get qaEmptyTitle;

  /// qa.list.empty.body.
  ///
  /// In ar, this message translates to:
  /// **'تظهر هنا الأسئلة والردود وإعلانات طارح المنافسة.'**
  String get qaEmptyMessage;

  /// M31 author: another participant (alias only).
  ///
  /// In ar, this message translates to:
  /// **'المتنافس {alias}'**
  String qaAuthorParticipant(int alias);

  /// M31 author as the issuer sees a participant.
  ///
  /// In ar, this message translates to:
  /// **'المتنافس {alias} · {organization}'**
  String qaAuthorParticipantNamed(int alias, String organization);

  /// M31 author: the viewer's organisation.
  ///
  /// In ar, this message translates to:
  /// **'أنتم'**
  String get qaAuthorMe;

  /// M31 author fallback.
  ///
  /// In ar, this message translates to:
  /// **'متنافس'**
  String get qaAuthorUnknown;

  /// M31 badge on an issuer's top-level post.
  ///
  /// In ar, this message translates to:
  /// **'إعلان'**
  String get qaAnnouncement;

  /// M31 pill when new comments arrive off screen.
  ///
  /// In ar, this message translates to:
  /// **'{count, plural, =1{رسالة جديدة} =2{رسالتان جديدتان} few{{count} رسائل جديدة} many{{count} رسالة جديدة} other{{count} رسالة جديدة}}'**
  String qaNewMessages(int count);

  /// M31 thread: replies count (semantics).
  ///
  /// In ar, this message translates to:
  /// **'{count, plural, =1{رد واحد} =2{ردّان} few{{count} ردود} many{{count} رداً} other{{count} رد}}'**
  String qaRepliesCount(int count);

  /// Issuer actions that are web-only on mobile (SCREENS §3.7, CD5).
  ///
  /// In ar, this message translates to:
  /// **'متاح في لوحة التحكم على الويب.'**
  String get issuerWebOnly;

  /// M38 notice when web-only issuer actions are open to the user.
  ///
  /// In ar, this message translates to:
  /// **'تمديد الإغلاق وجولة العرض النهائي والترسية وإلغاؤها والإغلاق دون ترسية متاحة في لوحة التحكم على الويب.'**
  String get issuerWebOnlyActions;

  /// S10 / §3.2: issuer_plan_required, always with billing.managed_on_web.
  ///
  /// In ar, this message translates to:
  /// **'تحتاج منشأتكم إلى باقة فعّالة لطرح المنافسات.'**
  String get issuerPlanRequired;

  /// Placeholder for a missing value.
  ///
  /// In ar, this message translates to:
  /// **'—'**
  String get issuerNoValue;

  /// Yes value in key-value lists.
  ///
  /// In ar, this message translates to:
  /// **'نعم'**
  String get issuerYes;

  /// No value in key-value lists.
  ///
  /// In ar, this message translates to:
  /// **'لا'**
  String get issuerNo;

  /// Confirm button of the unsaved-changes dialogs.
  ///
  /// In ar, this message translates to:
  /// **'تجاهل'**
  String get issuerDiscard;

  /// A participant by alias number.
  ///
  /// In ar, this message translates to:
  /// **'المتنافس {alias}'**
  String issuerParticipantAlias(int alias);

  /// A participant as the issuer sees it: alias and organisation.
  ///
  /// In ar, this message translates to:
  /// **'المتنافس {alias} · {name}'**
  String issuerParticipantLabel(int alias, String name);

  /// M33 create button (S10).
  ///
  /// In ar, this message translates to:
  /// **'منافسة جديدة'**
  String get issuerListCreate;

  /// M33 search hint.
  ///
  /// In ar, this message translates to:
  /// **'ابحث بعنوان المنافسة'**
  String get issuerListSearchHint;

  /// M33 segment: status_group=active.
  ///
  /// In ar, this message translates to:
  /// **'النشطة'**
  String get issuerListSegmentActive;

  /// M33 segment: status_group=draft.
  ///
  /// In ar, this message translates to:
  /// **'المسودات'**
  String get issuerListSegmentDrafts;

  /// M33 segment: status_group=ended.
  ///
  /// In ar, this message translates to:
  /// **'المنتهية'**
  String get issuerListSegmentEnded;

  /// M33 empty (active).
  ///
  /// In ar, this message translates to:
  /// **'لا توجد منافسات نشطة'**
  String get issuerListEmptyActiveTitle;

  /// M33 empty (active).
  ///
  /// In ar, this message translates to:
  /// **'تظهر هنا المنافسات المجدولة والمفتوحة للعروض وقيد التقييم.'**
  String get issuerListEmptyActiveMessage;

  /// M33 empty (drafts).
  ///
  /// In ar, this message translates to:
  /// **'لا توجد مسودات'**
  String get issuerListEmptyDraftsTitle;

  /// M33 empty (drafts).
  ///
  /// In ar, this message translates to:
  /// **'تُحفظ المنافسة الجديدة مسودةً حتى تنشرها.'**
  String get issuerListEmptyDraftsMessage;

  /// M33 empty (ended).
  ///
  /// In ar, this message translates to:
  /// **'لا توجد منافسات منتهية'**
  String get issuerListEmptyEndedTitle;

  /// M33 empty (ended).
  ///
  /// In ar, this message translates to:
  /// **'تظهر هنا المنافسات التي تمت ترسيتها أو أُغلقت دون ترسية أو أُلغيت.'**
  String get issuerListEmptyEndedMessage;

  /// M33 search without results.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد نتائج مطابقة'**
  String get issuerListNoResultsTitle;

  /// M33 search without results.
  ///
  /// In ar, this message translates to:
  /// **'جرّب كلمات بحث أخرى.'**
  String get issuerListNoResultsMessage;

  /// M33 row of a scheduled competition.
  ///
  /// In ar, this message translates to:
  /// **'تبدأ العروض في {time}'**
  String issuerListOpensAt(String time);

  /// M33 row of a draft.
  ///
  /// In ar, this message translates to:
  /// **'آخر تحديث في {date}'**
  String issuerListUpdatedAt(String date);

  /// M33 row counts.
  ///
  /// In ar, this message translates to:
  /// **'المدعوون {invited} · المنضمون {joined} · بعروض {withOffers}'**
  String issuerListCounts(int invited, int joined, int withOffers);

  /// Wizard title.
  ///
  /// In ar, this message translates to:
  /// **'منافسة جديدة'**
  String get issuerCreateTitle;

  /// M34 step title.
  ///
  /// In ar, this message translates to:
  /// **'النوع ومستوى القواعد'**
  String get issuerCreateStepType;

  /// M35 step title.
  ///
  /// In ar, this message translates to:
  /// **'البيانات الأساسية'**
  String get issuerCreateStepBasics;

  /// M36 step title.
  ///
  /// In ar, this message translates to:
  /// **'الأسعار والمواعيد'**
  String get issuerCreateStepSchedule;

  /// M37 step title.
  ///
  /// In ar, this message translates to:
  /// **'المراجعة والحفظ'**
  String get issuerCreateStepReview;

  /// M34 direction section.
  ///
  /// In ar, this message translates to:
  /// **'نوع المنافسة'**
  String get issuerCreateDirectionTitle;

  /// M34 direction card: the issuer role by direction.
  ///
  /// In ar, this message translates to:
  /// **'{direction, select, tender{أنت المشتري} auction{أنت البائع} other{طارح المنافسة}}'**
  String issuerCreateIssuerRole(String direction);

  /// S10 auction gate.
  ///
  /// In ar, this message translates to:
  /// **'المزايدات غير مفعّلة لمنشأتك. تواصل مع بافو.'**
  String get issuerCreateAuctionDisabled;

  /// M34 format section.
  ///
  /// In ar, this message translates to:
  /// **'طريقة تقديم العروض'**
  String get issuerCreateFormatTitle;

  /// M34 live format card.
  ///
  /// In ar, this message translates to:
  /// **'عروض مباشرة قابلة للتحسين حتى الإغلاق'**
  String get issuerCreateFormatLiveHint;

  /// M34 sealed format card.
  ///
  /// In ar, this message translates to:
  /// **'عرض مغلق لكل متنافس يُفتح عند الإغلاق'**
  String get issuerCreateFormatSealedHint;

  /// M34 preset section.
  ///
  /// In ar, this message translates to:
  /// **'الإعداد المسبق للقواعد'**
  String get issuerCreatePresetTitle;

  /// M34 validation.
  ///
  /// In ar, this message translates to:
  /// **'اختر إعداداً مسبقاً للمتابعة.'**
  String get issuerCreatePresetRequired;

  /// M34 when no preset matches the direction and format.
  ///
  /// In ar, this message translates to:
  /// **'لا يوجد إعداد مسبق لهذا الاختيار في التطبيق. يمكنك إنشاء هذه المنافسة من لوحة التحكم على الويب.'**
  String get issuerCreateNoPreset;

  /// M34 (CD4).
  ///
  /// In ar, this message translates to:
  /// **'الإعدادات المتقدمة متاحة في لوحة التحكم على الويب.'**
  String get issuerCreateAdvancedOnWeb;

  /// M36 prices section.
  ///
  /// In ar, this message translates to:
  /// **'الأسعار'**
  String get issuerCreatePricesTitle;

  /// M36 schedule section.
  ///
  /// In ar, this message translates to:
  /// **'المواعيد'**
  String get issuerCreateScheduleTitle;

  /// Wizard unsaved-changes guard.
  ///
  /// In ar, this message translates to:
  /// **'تجاهل المنافسة؟'**
  String get issuerCreateDiscardTitle;

  /// Wizard unsaved-changes guard.
  ///
  /// In ar, this message translates to:
  /// **'لن تُحفظ البيانات التي أدخلتها.'**
  String get issuerCreateDiscardMessage;

  /// M37 success toast.
  ///
  /// In ar, this message translates to:
  /// **'حُفظت المسودة. ادعُ المتنافسين وأضف المستندات ثم انشرها.'**
  String get issuerCreateSaved;

  /// Preset card chip.
  ///
  /// In ar, this message translates to:
  /// **'خطوة التحسين {step}'**
  String issuerPresetChipStep(String step);

  /// Preset card chip.
  ///
  /// In ar, this message translates to:
  /// **'الترتيب ظاهر'**
  String get issuerPresetChipRankFull;

  /// Preset card chip.
  ///
  /// In ar, this message translates to:
  /// **'مؤشر العرض المتصدر'**
  String get issuerPresetChipLeadingFlag;

  /// Preset card chip.
  ///
  /// In ar, this message translates to:
  /// **'الترتيب مخفي'**
  String get issuerPresetChipRankHidden;

  /// Preset card chip.
  ///
  /// In ar, this message translates to:
  /// **'الأسعار ظاهرة'**
  String get issuerPresetChipPricesShown;

  /// Preset card chip.
  ///
  /// In ar, this message translates to:
  /// **'تمديد تلقائي'**
  String get issuerPresetChipAutoExtend;

  /// Preset card chip.
  ///
  /// In ar, this message translates to:
  /// **'جولة عرض نهائي'**
  String get issuerPresetChipBafo;

  /// Title field.
  ///
  /// In ar, this message translates to:
  /// **'عنوان المنافسة'**
  String get issuerFieldTitle;

  /// Description field.
  ///
  /// In ar, this message translates to:
  /// **'الوصف ونطاق العمل'**
  String get issuerFieldDescription;

  /// Description helper (R18).
  ///
  /// In ar, this message translates to:
  /// **'مطلوب قبل النشر.'**
  String get issuerFieldDescriptionHelper;

  /// Other-category text (R15).
  ///
  /// In ar, this message translates to:
  /// **'حدّد الفئة'**
  String get issuerFieldCategoryOther;

  /// R14 category check.
  ///
  /// In ar, this message translates to:
  /// **'لا تسمح هذه الفئة بالمزايدات.'**
  String get issuerFieldCategoryNoAuction;

  /// rules.start_price.label by direction.
  ///
  /// In ar, this message translates to:
  /// **'{direction, select, tender{سعر السقف} auction{سعر الافتتاح} other{سعر البداية}}'**
  String issuerFieldStartPrice(String direction);

  /// Start price helper by direction.
  ///
  /// In ar, this message translates to:
  /// **'{direction, select, tender{اختياري. لا يُقبل عرض يتجاوزه.} auction{مطلوب قبل النشر. لا يُقبل عرض أقل منه.} other{اختياري.}}'**
  String issuerFieldStartPriceHelper(String direction);

  /// rules.reserve_price.label by direction.
  ///
  /// In ar, this message translates to:
  /// **'{direction, select, tender{السعر المستهدف} auction{الحد الأدنى المقبول} other{السعر المستهدف أو الحد الأدنى المقبول}}'**
  String issuerFieldReservePrice(String direction);

  /// Reserve helper.
  ///
  /// In ar, this message translates to:
  /// **'اختياري. مخفي عن المتنافسين ويؤثر في الترسية فقط.'**
  String get issuerFieldReserveHelper;

  /// R6 hint by direction.
  ///
  /// In ar, this message translates to:
  /// **'{direction, select, tender{يجب ألا يزيد السعر المستهدف عن سعر السقف.} auction{يجب ألا يقل الحد الأدنى المقبول عن سعر الافتتاح.} other{السعر المستهدف أو الحد الأدنى المقبول لا يتوافق مع سعر البداية.}}'**
  String issuerFieldReserveVersusStart(String direction);

  /// Opening option.
  ///
  /// In ar, this message translates to:
  /// **'فور النشر'**
  String get issuerFieldOpensOnPublish;

  /// Opening option.
  ///
  /// In ar, this message translates to:
  /// **'في موعد محدد'**
  String get issuerFieldOpensAtTime;

  /// Date field helper.
  ///
  /// In ar, this message translates to:
  /// **'المواعيد بتوقيت الرياض.'**
  String get issuerFieldRiyadhTime;

  /// R16 hint.
  ///
  /// In ar, this message translates to:
  /// **'يجب أن يكون موعد الإغلاق بعد بدء استقبال العروض.'**
  String get issuerFieldCloseBeforeOpen;

  /// R16 hint.
  ///
  /// In ar, this message translates to:
  /// **'اختر موعداً لم يمضِ بعد.'**
  String get issuerFieldOpensInPast;

  /// R16 minimum duration.
  ///
  /// In ar, this message translates to:
  /// **'يجب ألا تقل مدة استقبال العروض عن {minutes, plural, =1{دقيقة واحدة} =2{دقيقتين} few{{minutes} دقائق} many{{minutes} دقيقة} other{{minutes} دقيقة}}.'**
  String issuerFieldDurationTooShort(int minutes);

  /// R16 maximum duration.
  ///
  /// In ar, this message translates to:
  /// **'يجب ألا تزيد المدة عن {days, plural, =1{يوم واحد} =2{يومين} few{{days} أيام} many{{days} يوماً} other{{days} يوم}}.'**
  String issuerFieldDurationTooLong(int days);

  /// G3 preview card.
  ///
  /// In ar, this message translates to:
  /// **'الجدول الزمني المتوقع'**
  String get issuerSchedulePreviewTitle;

  /// G3 label.
  ///
  /// In ar, this message translates to:
  /// **'تقديري، يُثبَّت عند النشر'**
  String get issuerSchedulePreviewNote;

  /// M37 edit link of a step.
  ///
  /// In ar, this message translates to:
  /// **'تعديل'**
  String get issuerReviewEdit;

  /// M37 primary button.
  ///
  /// In ar, this message translates to:
  /// **'حفظ المسودة'**
  String get issuerReviewSave;

  /// M37 note.
  ///
  /// In ar, this message translates to:
  /// **'تُحفظ المنافسة مسودةً. يمكنك بعدها إضافة المستندات ودعوة المتنافسين ثم نشرها.'**
  String get issuerReviewDraftNote;

  /// M37 description summary.
  ///
  /// In ar, this message translates to:
  /// **'لم يُضف بعد (مطلوب قبل النشر)'**
  String get issuerReviewNoDescription;

  /// M37 description summary.
  ///
  /// In ar, this message translates to:
  /// **'أُضيف'**
  String get issuerReviewDescriptionAdded;

  /// M38 action sheet.
  ///
  /// In ar, this message translates to:
  /// **'الإجراءات'**
  String get issuerActionsMenu;

  /// M38 action.
  ///
  /// In ar, this message translates to:
  /// **'نشر المنافسة'**
  String get issuerActionPublish;

  /// M38 action.
  ///
  /// In ar, this message translates to:
  /// **'تعديل البيانات'**
  String get issuerActionEdit;

  /// M38 action.
  ///
  /// In ar, this message translates to:
  /// **'دعوة متنافسين'**
  String get issuerActionInvite;

  /// M41 action.
  ///
  /// In ar, this message translates to:
  /// **'دعوة المزيد'**
  String get issuerActionInviteMore;

  /// M38 action.
  ///
  /// In ar, this message translates to:
  /// **'المستندات'**
  String get issuerActionDocuments;

  /// Web-only action row.
  ///
  /// In ar, this message translates to:
  /// **'تمديد الإغلاق'**
  String get issuerActionExtend;

  /// Web-only action row.
  ///
  /// In ar, this message translates to:
  /// **'بدء جولة العرض النهائي'**
  String get issuerActionStartBafo;

  /// Web-only action row.
  ///
  /// In ar, this message translates to:
  /// **'الترسية'**
  String get issuerActionAward;

  /// Web-only action row.
  ///
  /// In ar, this message translates to:
  /// **'إلغاء الترسية'**
  String get issuerActionRevokeAward;

  /// Web-only action row.
  ///
  /// In ar, this message translates to:
  /// **'إغلاق دون ترسية'**
  String get issuerActionCloseWithoutAward;

  /// M44 destructive action.
  ///
  /// In ar, this message translates to:
  /// **'إلغاء المنافسة'**
  String get issuerActionCancel;

  /// M45 destructive action.
  ///
  /// In ar, this message translates to:
  /// **'حذف المسودة'**
  String get issuerActionDeleteDraft;

  /// M38 badge when source = api.
  ///
  /// In ar, this message translates to:
  /// **'أُنشئت عبر واجهة برمجة التطبيقات'**
  String get issuerDetailCreatedViaApi;

  /// Leading amount label.
  ///
  /// In ar, this message translates to:
  /// **'العرض المتصدر'**
  String get issuerDetailLeadingOffer;

  /// M38 counts section.
  ///
  /// In ar, this message translates to:
  /// **'ملخص المشاركة'**
  String get issuerDetailCounts;

  /// M38 created_by.
  ///
  /// In ar, this message translates to:
  /// **'أنشأها'**
  String get issuerDetailCreatedBy;

  /// Count tile.
  ///
  /// In ar, this message translates to:
  /// **'المدعوون'**
  String get issuerCountInvitations;

  /// Count tile.
  ///
  /// In ar, this message translates to:
  /// **'المنضمون'**
  String get issuerCountJoined;

  /// Count tile.
  ///
  /// In ar, this message translates to:
  /// **'المعتذرون'**
  String get issuerCountDeclined;

  /// Count tile.
  ///
  /// In ar, this message translates to:
  /// **'قدّموا عروضاً'**
  String get issuerCountWithOffers;

  /// Count tile.
  ///
  /// In ar, this message translates to:
  /// **'العروض'**
  String get issuerCountOffers;

  /// Count tile.
  ///
  /// In ar, this message translates to:
  /// **'الاستفسارات'**
  String get issuerCountComments;

  /// M38 link to M46.
  ///
  /// In ar, this message translates to:
  /// **'المتابعة المباشرة'**
  String get issuerNavLive;

  /// M38 link to M47.
  ///
  /// In ar, this message translates to:
  /// **'سجل العروض'**
  String get issuerNavOffers;

  /// M38 link to M41.
  ///
  /// In ar, this message translates to:
  /// **'المتنافسون والدعوات'**
  String get issuerNavParticipants;

  /// M38 link to M31.
  ///
  /// In ar, this message translates to:
  /// **'الاستفسارات'**
  String get issuerNavQa;

  /// M38 link to M48.
  ///
  /// In ar, this message translates to:
  /// **'التقييم والترسية'**
  String get issuerNavAward;

  /// M38 link to M42.
  ///
  /// In ar, this message translates to:
  /// **'المستندات'**
  String get issuerNavDocuments;

  /// M38 documents section action.
  ///
  /// In ar, this message translates to:
  /// **'إدارة'**
  String get issuerDocumentsManage;

  /// M38 award summary.
  ///
  /// In ar, this message translates to:
  /// **'تمت الترسية على {participant}'**
  String issuerAwardSummary(String participant);

  /// Timeline row.
  ///
  /// In ar, this message translates to:
  /// **'النشر'**
  String get issuerTimelinePublished;

  /// Timeline row.
  ///
  /// In ar, this message translates to:
  /// **'موعد الإغلاق المجدول'**
  String get issuerTimelineScheduledClose;

  /// Timeline row.
  ///
  /// In ar, this message translates to:
  /// **'موعد الإغلاق الحالي'**
  String get issuerTimelineEffectiveClose;

  /// Timeline row.
  ///
  /// In ar, this message translates to:
  /// **'أقصى موعد للإغلاق'**
  String get issuerTimelineHardStop;

  /// Timeline row.
  ///
  /// In ar, this message translates to:
  /// **'مرات التمديد'**
  String get issuerTimelineExtensions;

  /// Timeline row.
  ///
  /// In ar, this message translates to:
  /// **'الإغلاق'**
  String get issuerTimelineClosed;

  /// Timeline row.
  ///
  /// In ar, this message translates to:
  /// **'الترسية'**
  String get issuerTimelineAwarded;

  /// Timeline row.
  ///
  /// In ar, this message translates to:
  /// **'الإغلاق دون ترسية'**
  String get issuerTimelineNotAwarded;

  /// Timeline row.
  ///
  /// In ar, this message translates to:
  /// **'الإلغاء'**
  String get issuerTimelineCancelled;

  /// Setup checklist title.
  ///
  /// In ar, this message translates to:
  /// **'قبل النشر'**
  String get issuerChecklistTitle;

  /// Checklist (R18).
  ///
  /// In ar, this message translates to:
  /// **'أضف الوصف ونطاق العمل'**
  String get issuerChecklistDescription;

  /// Checklist (R16).
  ///
  /// In ar, this message translates to:
  /// **'حدّد موعد الإغلاق'**
  String get issuerChecklistSchedule;

  /// Checklist (R5).
  ///
  /// In ar, this message translates to:
  /// **'حدّد سعر الافتتاح'**
  String get issuerChecklistStartPrice;

  /// Checklist (R15).
  ///
  /// In ar, this message translates to:
  /// **'حدّد الفئة'**
  String get issuerChecklistOtherText;

  /// Checklist (R17).
  ///
  /// In ar, this message translates to:
  /// **'ادعُ {min, plural, =1{متنافساً واحداً} =2{متنافسَين} few{{min} متنافسين} many{{min} متنافساً} other{{min} متنافس}} على الأقل (المدعوون الآن: {current})'**
  String issuerChecklistInvitations(int min, int current);

  /// M43 sheet title.
  ///
  /// In ar, this message translates to:
  /// **'نشر المنافسة'**
  String get issuerPublishTitle;

  /// M43 note.
  ///
  /// In ar, this message translates to:
  /// **'عند النشر تُرسل الدعوات إلى المدعوين، وتصبح القواعد ثابتة.'**
  String get issuerPublishNote;

  /// M43 confirm button.
  ///
  /// In ar, this message translates to:
  /// **'نشر'**
  String get issuerPublishConfirm;

  /// M43 success toast.
  ///
  /// In ar, this message translates to:
  /// **'نُشرت المنافسة وأُرسلت الدعوات'**
  String get issuerPublishDone;

  /// M43 validation_failed title.
  ///
  /// In ar, this message translates to:
  /// **'أكمل ما يلي ثم أعد المحاولة:'**
  String get issuerPublishFixFields;

  /// M43 min_participants_not_met (required − current).
  ///
  /// In ar, this message translates to:
  /// **'أضف {count, plural, =1{مدعواً واحداً} =2{مدعوَّين} few{{count} مدعوين} many{{count} مدعواً} other{{count} مدعو}} على الأقل.'**
  String issuerPublishMissingInvitations(int count);

  /// M43 live_event_capacity_reached.
  ///
  /// In ar, this message translates to:
  /// **'بلغ عدد الفعاليات المباشرة المتزامنة حدّه في هذه الفترة. اختر موعداً آخر.'**
  String get issuerPublishCapacity;

  /// M44 sheet title.
  ///
  /// In ar, this message translates to:
  /// **'إلغاء المنافسة'**
  String get issuerCancelTitle;

  /// M44 success toast.
  ///
  /// In ar, this message translates to:
  /// **'أُلغيت المنافسة.'**
  String get issuerCancelDone;

  /// M45 dialog title.
  ///
  /// In ar, this message translates to:
  /// **'حذف المسودة؟'**
  String get issuerDeleteTitle;

  /// M45 dialog message.
  ///
  /// In ar, this message translates to:
  /// **'تُحذف المسودة ودعواتها، ولا يمكن استعادتها.'**
  String get issuerDeleteMessage;

  /// M45 success toast.
  ///
  /// In ar, this message translates to:
  /// **'حُذفت المسودة.'**
  String get issuerDeleteDone;

  /// Sponsorship card.
  ///
  /// In ar, this message translates to:
  /// **'رسوم المشاركة'**
  String get issuerSponsorshipTitle;

  /// Sponsorship mode.
  ///
  /// In ar, this message translates to:
  /// **'{mode, select, all{تغطية الرسوم لجميع المدعوين} selected{تغطية الرسوم لمدعوين محددين} other{لا تغطية للرسوم}}'**
  String issuerSponsorshipMode(String mode);

  /// Sponsorship card.
  ///
  /// In ar, this message translates to:
  /// **'الحد الأقصى للمتنافسين المغطّين'**
  String get issuerSponsorshipCap;

  /// Sponsorship card.
  ///
  /// In ar, this message translates to:
  /// **'التصاريح الممولة'**
  String get issuerSponsorshipFunded;

  /// Sponsorship card.
  ///
  /// In ar, this message translates to:
  /// **'تصاريح مستخدمة'**
  String get issuerSponsorshipJoined;

  /// Sponsorship card.
  ///
  /// In ar, this message translates to:
  /// **'تصاريح محجوزة'**
  String get issuerSponsorshipReserved;

  /// Sponsorship card.
  ///
  /// In ar, this message translates to:
  /// **'تصاريح متاحة'**
  String get issuerSponsorshipFreeSlots;

  /// Sponsorship card.
  ///
  /// In ar, this message translates to:
  /// **'بانتظار الدفع'**
  String get issuerSponsorshipPending;

  /// Sponsorship card.
  ///
  /// In ar, this message translates to:
  /// **'تصاريح غير مستخدمة'**
  String get issuerSponsorshipUnused;

  /// Sponsorship card (entitlement-only, §3.2).
  ///
  /// In ar, this message translates to:
  /// **'تُدار رسوم المشاركة ومدفوعاتها من لوحة تحكم بافو على الويب.'**
  String get issuerSponsorshipOnWeb;

  /// M39 title.
  ///
  /// In ar, this message translates to:
  /// **'تعديل المنافسة'**
  String get issuerEditTitle;

  /// M39 success toast.
  ///
  /// In ar, this message translates to:
  /// **'حُفظت التعديلات.'**
  String get issuerEditSaved;

  /// W16 note.
  ///
  /// In ar, this message translates to:
  /// **'سيُبلَّغ المتنافسون بالتحديث.'**
  String get issuerEditNotifyNote;

  /// W16 note (scheduled).
  ///
  /// In ar, this message translates to:
  /// **'القواعد والأسعار ثابتة بعد النشر.'**
  String get issuerEditRulesFixed;

  /// W16 note (live).
  ///
  /// In ar, this message translates to:
  /// **'أثناء استقبال العروض يمكن تعديل العنوان والوصف فقط.'**
  String get issuerEditLiveScope;

  /// M39 draft note.
  ///
  /// In ar, this message translates to:
  /// **'يُغيَّر نوع المنافسة وطريقة العروض والقواعد من لوحة التحكم على الويب.'**
  String get issuerEditTypeOnWeb;

  /// M39 unsaved-changes guard.
  ///
  /// In ar, this message translates to:
  /// **'تجاهل التعديلات؟'**
  String get issuerEditDiscardTitle;

  /// M39 unsaved-changes guard.
  ///
  /// In ar, this message translates to:
  /// **'لن تُحفظ التعديلات التي أجريتها.'**
  String get issuerEditDiscardMessage;

  /// M40 title.
  ///
  /// In ar, this message translates to:
  /// **'دعوة متنافسين'**
  String get issuerInviteTitle;

  /// M40 segment.
  ///
  /// In ar, this message translates to:
  /// **'مقترحون'**
  String get issuerInviteTabSuggestions;

  /// M40 segment.
  ///
  /// In ar, this message translates to:
  /// **'بالبريد'**
  String get issuerInviteTabEmail;

  /// M40 segment.
  ///
  /// In ar, this message translates to:
  /// **'دليل الموردين'**
  String get issuerInviteTabVendors;

  /// M40 search.
  ///
  /// In ar, this message translates to:
  /// **'ابحث باسم المنشأة'**
  String get issuerInviteSearchSuggestions;

  /// M40 search.
  ///
  /// In ar, this message translates to:
  /// **'ابحث في دليل الموردين'**
  String get issuerInviteSearchVendors;

  /// M40 empty.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد منشآت مقترحة مطابقة.'**
  String get issuerInviteNoSuggestions;

  /// M40 empty.
  ///
  /// In ar, this message translates to:
  /// **'لا يوجد موردون مطابقون.'**
  String get issuerInviteNoVendors;

  /// Verified badge label.
  ///
  /// In ar, this message translates to:
  /// **'منشأة موثّقة'**
  String get issuerInviteVerified;

  /// Suggestion match chip.
  ///
  /// In ar, this message translates to:
  /// **'نفس الفئة'**
  String get issuerInviteMatchCategory;

  /// Suggestion match chip.
  ///
  /// In ar, this message translates to:
  /// **'نفس المنطقة'**
  String get issuerInviteMatchRegion;

  /// Suggestion chip.
  ///
  /// In ar, this message translates to:
  /// **'لديها باقة فعّالة'**
  String get issuerInviteHasPlan;

  /// Vendor with a BAFO account.
  ///
  /// In ar, this message translates to:
  /// **'مسجّل في بافو'**
  String get issuerInviteVendorRegistered;

  /// M40 e-mail field.
  ///
  /// In ar, this message translates to:
  /// **'عناوين البريد الإلكتروني'**
  String get issuerInviteEmailsLabel;

  /// M40 e-mail helper.
  ///
  /// In ar, this message translates to:
  /// **'افصل بين العناوين بفاصلة أو مسافة أو سطر جديد.'**
  String get issuerInviteEmailsHelper;

  /// M40 add e-mails.
  ///
  /// In ar, this message translates to:
  /// **'إضافة'**
  String get issuerInviteEmailsAdd;

  /// M40 toast after adding e-mails.
  ///
  /// In ar, this message translates to:
  /// **'{count, plural, =1{أُضيف عنوان واحد.} =2{أُضيف عنوانان.} few{أُضيفت {count} عناوين.} many{أُضيف {count} عنواناً.} other{أُضيف {count} عنوان.}}'**
  String issuerInviteEmailsAdded(int count);

  /// M40 invalid pasted e-mails.
  ///
  /// In ar, this message translates to:
  /// **'عناوين غير صالحة: {emails}'**
  String issuerInviteEmailsInvalid(String emails);

  /// M40 staged header.
  ///
  /// In ar, this message translates to:
  /// **'المختارون ({count})'**
  String issuerInviteStaged(int count);

  /// M40 staged empty.
  ///
  /// In ar, this message translates to:
  /// **'اختر من المقترحين أو دليل الموردين، أو أضف عناوين بريد.'**
  String get issuerInviteStagedEmpty;

  /// Remove a staged row.
  ///
  /// In ar, this message translates to:
  /// **'إزالة {name}'**
  String issuerInviteRemove(String name);

  /// Selected mode switch.
  ///
  /// In ar, this message translates to:
  /// **'تغطية رسوم المشاركة'**
  String get issuerInviteCoverFees;

  /// Free sponsored slots (selected mode).
  ///
  /// In ar, this message translates to:
  /// **'{count, plural, =0{لا توجد تصاريح مغطّاة متاحة.} =1{تصريح مغطّى واحد متاح.} =2{تصريحان مغطّيان متاحان.} few{{count} تصاريح مغطّاة متاحة.} many{{count} تصريحاً مغطّى متاحاً.} other{{count} تصريح مغطّى متاح.}}'**
  String issuerInviteFreeSlots(int count);

  /// All-or-nothing row errors.
  ///
  /// In ar, this message translates to:
  /// **'لم تُرسل أي دعوة. صحّح الصفوف المحددة ثم أعد المحاولة.'**
  String get issuerInviteNothingSent;

  /// sponsorship_payment_required on invite (§3.2).
  ///
  /// In ar, this message translates to:
  /// **'تُدفع رسوم مشاركة هذه الدعوات من لوحة تحكم بافو على الويب. لم تُرسل الدعوات.'**
  String get issuerInviteFeesOnWeb;

  /// Selected mode.
  ///
  /// In ar, this message translates to:
  /// **'إرسال دون تغطية الرسوم'**
  String get issuerInviteSendWithoutFees;

  /// Disabled send button.
  ///
  /// In ar, this message translates to:
  /// **'اختر المدعوين أولاً'**
  String get issuerInviteChooseFirst;

  /// M40 send button.
  ///
  /// In ar, this message translates to:
  /// **'{count, plural, =1{إرسال دعوة واحدة} =2{إرسال دعوتين} few{إرسال {count} دعوات} many{إرسال {count} دعوة} other{إرسال {count} دعوة}}'**
  String issuerInviteSend(int count);

  /// M40 button on a draft.
  ///
  /// In ar, this message translates to:
  /// **'{count, plural, =1{إضافة مدعو واحد} =2{إضافة مدعوَّين} few{إضافة {count} مدعوين} many{إضافة {count} مدعواً} other{إضافة {count} مدعو}}'**
  String issuerInviteAdd(int count);

  /// M40 success toast.
  ///
  /// In ar, this message translates to:
  /// **'أُرسلت الدعوات.'**
  String get issuerInviteDone;

  /// M40 success on a draft.
  ///
  /// In ar, this message translates to:
  /// **'أُضيف المدعوون إلى المسودة، وتُرسل الدعوات عند النشر.'**
  String get issuerInviteAddedToDraft;

  /// item_codes: invitation_duplicate.
  ///
  /// In ar, this message translates to:
  /// **'مدعو من قبل في هذه المنافسة.'**
  String get issuerInviteRowDuplicate;

  /// item_codes: cannot_invite_own_organization.
  ///
  /// In ar, this message translates to:
  /// **'لا يمكن دعوة منشأتكم.'**
  String get issuerInviteRowOwnOrganization;

  /// item_codes: vendor_blocked.
  ///
  /// In ar, this message translates to:
  /// **'هذا المورد محظور في دليلكم.'**
  String get issuerInviteRowVendorBlocked;

  /// item_codes: vendor_not_found.
  ///
  /// In ar, this message translates to:
  /// **'لم يُعثر على هذا المورد.'**
  String get issuerInviteRowVendorNotFound;

  /// M40 unsaved guard.
  ///
  /// In ar, this message translates to:
  /// **'تجاهل المختارين؟'**
  String get issuerInviteDiscardTitle;

  /// M40 unsaved guard.
  ///
  /// In ar, this message translates to:
  /// **'لم تُرسل الدعوات بعد، ولن يُحفظ اختيارك.'**
  String get issuerInviteDiscardMessage;

  /// M41 title.
  ///
  /// In ar, this message translates to:
  /// **'المتنافسون والدعوات'**
  String get issuerParticipantsTitle;

  /// M41 filter.
  ///
  /// In ar, this message translates to:
  /// **'الكل ({count})'**
  String issuerParticipantsFilterAll(int count);

  /// M41 filter.
  ///
  /// In ar, this message translates to:
  /// **'{status} ({count})'**
  String issuerParticipantsFilterStatus(String status, int count);

  /// M41 filter empty.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد دعوات بهذه الحالة.'**
  String get issuerParticipantsFilterEmpty;

  /// M41 empty.
  ///
  /// In ar, this message translates to:
  /// **'لم تُرسل دعوات بعد'**
  String get issuerParticipantsEmptyTitle;

  /// M41 empty.
  ///
  /// In ar, this message translates to:
  /// **'ادعُ المتنافسين من المقترحين أو بالبريد الإلكتروني أو من دليل الموردين.'**
  String get issuerParticipantsEmptyMessage;

  /// invitations.status.* (S2).
  ///
  /// In ar, this message translates to:
  /// **'{status, select, draft{مسودة} sent{أُرسلت} viewed{اطُّلع عليها} joined{انضم} declined{اعتذر} revoked{أُلغيت} expired{انتهت} other{غير معروفة}}'**
  String issuerInvitationStatus(String status);

  /// sponsorship.coverage.* (S2).
  ///
  /// In ar, this message translates to:
  /// **'{coverage, select, sponsored{مغطّاة منكم} own_plan{باقة المتنافس} none{غير مغطّاة} other{غير معروفة}}'**
  String issuerCoverage(String coverage);

  /// sponsorship.pass_status.* (S2).
  ///
  /// In ar, this message translates to:
  /// **'{status, select, pending{تصريح بانتظار الدفع} reserved{تصريح محجوز} joined{تصريح مستخدَم} released{تصريح مُعاد} unused{تصريح غير مستخدَم} void{تصريح ملغى} other{تصريح}}'**
  String issuerPassStatus(String status);

  /// Invitation history.
  ///
  /// In ar, this message translates to:
  /// **'أُرسلت {time}'**
  String issuerInvitationSentAt(String time);

  /// Invitation history.
  ///
  /// In ar, this message translates to:
  /// **'اطُّلع عليها {time}'**
  String issuerInvitationViewedAt(String time);

  /// Invitation history.
  ///
  /// In ar, this message translates to:
  /// **'انضم {time}'**
  String issuerInvitationJoinedAt(String time);

  /// Invitation history.
  ///
  /// In ar, this message translates to:
  /// **'اعتذر {time}'**
  String issuerInvitationDeclinedAt(String time);

  /// Invitation history.
  ///
  /// In ar, this message translates to:
  /// **'أُلغيت {time}'**
  String issuerInvitationRevokedAt(String time);

  /// Decline reason.
  ///
  /// In ar, this message translates to:
  /// **'السبب: {reason}'**
  String issuerInvitationReason(String reason);

  /// M41 action.
  ///
  /// In ar, this message translates to:
  /// **'إعادة الإرسال'**
  String get issuerInvitationResend;

  /// M41 destructive action.
  ///
  /// In ar, this message translates to:
  /// **'إلغاء الدعوة'**
  String get issuerInvitationRevoke;

  /// M41 destructive action (draft).
  ///
  /// In ar, this message translates to:
  /// **'إزالة المدعو'**
  String get issuerInvitationRemove;

  /// M41 toast.
  ///
  /// In ar, this message translates to:
  /// **'أُعيد إرسال الدعوة.'**
  String get issuerInvitationResent;

  /// M41 toast.
  ///
  /// In ar, this message translates to:
  /// **'أُلغيت الدعوة.'**
  String get issuerInvitationRevoked;

  /// M41 toast.
  ///
  /// In ar, this message translates to:
  /// **'أُزيل المدعو من المسودة.'**
  String get issuerInvitationRemoved;

  /// Resend 429.
  ///
  /// In ar, this message translates to:
  /// **'بلغت الحد اليومي لإعادة إرسال هذه الدعوة.'**
  String get issuerInvitationResendLimit;

  /// M41 confirm.
  ///
  /// In ar, this message translates to:
  /// **'إلغاء الدعوة؟'**
  String get issuerRevokeTitle;

  /// M41 confirm.
  ///
  /// In ar, this message translates to:
  /// **'لن يتمكن المدعو من الانضمام بهذه الدعوة.'**
  String get issuerRevokeMessage;

  /// M41 confirm (draft).
  ///
  /// In ar, this message translates to:
  /// **'إزالة المدعو؟'**
  String get issuerRemoveInviteeTitle;

  /// M41 confirm (draft).
  ///
  /// In ar, this message translates to:
  /// **'يُحذف هذا المدعو من المسودة.'**
  String get issuerRemoveInviteeMessage;

  /// M42 title.
  ///
  /// In ar, this message translates to:
  /// **'مستندات المنافسة'**
  String get issuerDocumentsTitle;

  /// M42 upload button.
  ///
  /// In ar, this message translates to:
  /// **'رفع مستند'**
  String get issuerDocumentsUpload;

  /// M42 limits.
  ///
  /// In ar, this message translates to:
  /// **'PDF أو Word أو Excel أو صور أو ZIP، بحد أقصى 100 ميجابايت للملف.'**
  String get issuerDocumentsLimits;

  /// M42 addendum note.
  ///
  /// In ar, this message translates to:
  /// **'المستندات المضافة بعد النشر تُعلَن للمتنافسين ملحقاً.'**
  String get issuerDocumentsAddendumNote;

  /// M42 note (live).
  ///
  /// In ar, this message translates to:
  /// **'لا يمكن حذف المستندات بعد بدء استقبال العروض.'**
  String get issuerDocumentsDeleteClosed;

  /// M42 web-only note.
  ///
  /// In ar, this message translates to:
  /// **'الروابط الخارجية ومستندات الدعوة تُضاف من لوحة التحكم على الويب.'**
  String get issuerDocumentsWebOnly;

  /// M42 empty.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد مستندات'**
  String get issuerDocumentsEmptyTitle;

  /// M42 empty.
  ///
  /// In ar, this message translates to:
  /// **'ارفع كراسة الشروط والمواصفات ليطّلع عليها المتنافسون بعد انضمامهم.'**
  String get issuerDocumentsEmptyMessage;

  /// M42 upload error.
  ///
  /// In ar, this message translates to:
  /// **'تعذّر رفع الملف.'**
  String get issuerDocumentsUploadFailed;

  /// M42 progress label.
  ///
  /// In ar, this message translates to:
  /// **'جارٍ رفع {name}'**
  String issuerDocumentsUploading(String name);

  /// M42 confirm.
  ///
  /// In ar, this message translates to:
  /// **'حذف المستند؟'**
  String get issuerDocumentDeleteTitle;

  /// M42 confirm.
  ///
  /// In ar, this message translates to:
  /// **'يُحذف المستند {name} من المنافسة.'**
  String issuerDocumentDeleteMessage(String name);

  /// M42 destructive confirm.
  ///
  /// In ar, this message translates to:
  /// **'حذف المستند'**
  String get issuerDocumentDelete;

  /// M46 title.
  ///
  /// In ar, this message translates to:
  /// **'المتابعة المباشرة'**
  String get issuerLiveTitle;

  /// S4 connected indicator.
  ///
  /// In ar, this message translates to:
  /// **'مباشر'**
  String get issuerLiveConnected;

  /// S4 reconnecting.
  ///
  /// In ar, this message translates to:
  /// **'جارٍ إعادة الاتصال…'**
  String get issuerLiveReconnecting;

  /// S4 polling (3 or 10 s).
  ///
  /// In ar, this message translates to:
  /// **'التحديثات المباشرة متأخرة، ونحدّث كل {seconds} ثوانٍ.'**
  String issuerLivePolling(int seconds);

  /// M46 on a draft.
  ///
  /// In ar, this message translates to:
  /// **'لم تُنشر المنافسة بعد'**
  String get issuerLiveDraftTitle;

  /// M46 on a draft.
  ///
  /// In ar, this message translates to:
  /// **'تبدأ المتابعة المباشرة بعد نشر المنافسة.'**
  String get issuerLiveDraftMessage;

  /// online_participants_count.
  ///
  /// In ar, this message translates to:
  /// **'{count, plural, =0{لا يوجد متنافسون متصلون الآن} =1{متنافس واحد متصل الآن} =2{متنافسان متصلان الآن} few{{count} متنافسين متصلون الآن} many{{count} متنافساً متصلاً الآن} other{{count} متنافس متصل الآن}}'**
  String issuerLiveOnline(int count);

  /// last_change.reason of an extension.
  ///
  /// In ar, this message translates to:
  /// **'{reason, select, auto{مُدّد الإغلاق تلقائياً بسبب عرض في الدقائق الأخيرة.} manual{مدّد طارح المنافسة موعد الإغلاق.} admin{مدّدت بافو موعد الإغلاق.} other{مُدّد موعد الإغلاق.}}'**
  String issuerLiveExtended(String reason);

  /// BAFO round progress.
  ///
  /// In ar, this message translates to:
  /// **'قدّم {submitted} من {shortlist} عروضهم النهائية'**
  String issuerLiveBafoProgress(int submitted, int shortlist);

  /// No leader yet.
  ///
  /// In ar, this message translates to:
  /// **'لم تصل عروض بعد'**
  String get issuerLiveNoOffers;

  /// Sealed lock.
  ///
  /// In ar, this message translates to:
  /// **'تُفتح العروض عند الإغلاق'**
  String get issuerLiveSealedLock;

  /// Leader accepted_at.
  ///
  /// In ar, this message translates to:
  /// **'استُلم في {time}'**
  String issuerLiveReceivedAt(String time);

  /// ReserveMetIndicator (issuer only).
  ///
  /// In ar, this message translates to:
  /// **'{direction, select, tender{تحقق السعر المستهدف} auction{تحقق الحد الأدنى المقبول} other{تحقق السعر المستهدف أو الحد الأدنى المقبول}}'**
  String issuerLiveReserveMet(String direction);

  /// ReserveMetIndicator (issuer only).
  ///
  /// In ar, this message translates to:
  /// **'{direction, select, tender{لم يتحقق السعر المستهدف} auction{لم يتحقق الحد الأدنى المقبول} other{لم يتحقق السعر المستهدف أو الحد الأدنى المقبول}}'**
  String issuerLiveReserveNotMet(String direction);

  /// live.metric.improvement (server-signed).
  ///
  /// In ar, this message translates to:
  /// **'{direction, select, tender{التوفير مقارنة بسعر السقف} auction{الزيادة مقارنة بسعر الافتتاح} other{التحسّن مقارنة بسعر البداية}}'**
  String issuerLiveImprovement(String direction);

  /// Throttled live region.
  ///
  /// In ar, this message translates to:
  /// **'العرض المتصدر الآن من {participant}: {amount}'**
  String issuerLiveLeaderAnnouncement(String participant, String amount);

  /// M46 ranking.
  ///
  /// In ar, this message translates to:
  /// **'ترتيب المتنافسين'**
  String get issuerLiveRankingTitle;

  /// Empty ranking.
  ///
  /// In ar, this message translates to:
  /// **'لم ينضم متنافسون بعد.'**
  String get issuerLiveNoParticipants;

  /// Rank semantics label.
  ///
  /// In ar, this message translates to:
  /// **'المرتبة {rank}'**
  String issuerLiveRank(int rank);

  /// Sealed row.
  ///
  /// In ar, this message translates to:
  /// **'قدّم عرضاً'**
  String get issuerLiveSubmitted;

  /// Sealed row.
  ///
  /// In ar, this message translates to:
  /// **'لم يقدّم عرضاً بعد'**
  String get issuerLiveNotSubmitted;

  /// Ranking row.
  ///
  /// In ar, this message translates to:
  /// **'العرض الأول: {amount}'**
  String issuerLiveFirstOffer(String amount);

  /// Offers per participant.
  ///
  /// In ar, this message translates to:
  /// **'{count, plural, =0{لا عروض} =1{عرض واحد} =2{عرضان} few{{count} عروض} many{{count} عرضاً} other{{count} عرض}}'**
  String issuerLiveOffersCount(int count);

  /// Ranking row.
  ///
  /// In ar, this message translates to:
  /// **'آخر عرض {time}'**
  String issuerLiveLastOffer(String time);

  /// BAFO flag.
  ///
  /// In ar, this message translates to:
  /// **'في القائمة المختصرة'**
  String get issuerLiveBafoShortlisted;

  /// BAFO flag.
  ///
  /// In ar, this message translates to:
  /// **'قدّم العرض النهائي'**
  String get issuerLiveBafoSubmitted;

  /// M46 (CD5).
  ///
  /// In ar, this message translates to:
  /// **'تمديد الإغلاق متاح في لوحة التحكم على الويب.'**
  String get issuerLiveExtendOnWeb;

  /// M47 title.
  ///
  /// In ar, this message translates to:
  /// **'سجل العروض'**
  String get issuerOffersTitle;

  /// M47 empty.
  ///
  /// In ar, this message translates to:
  /// **'لم تصل عروض بعد'**
  String get issuerOffersEmptyTitle;

  /// M47 empty.
  ///
  /// In ar, this message translates to:
  /// **'تظهر العروض هنا فور استلامها.'**
  String get issuerOffersEmptyMessage;

  /// seq semantics label.
  ///
  /// In ar, this message translates to:
  /// **'العرض رقم {seq}'**
  String issuerOfferSeq(int seq);

  /// Sealed amount (null).
  ///
  /// In ar, this message translates to:
  /// **'مغلق'**
  String get issuerOfferSealed;

  /// offers.stage.* (S2).
  ///
  /// In ar, this message translates to:
  /// **'{stage, select, sealed{مغلق} initial{أولي} live{مباشر} bafo{نهائي} other{—}}'**
  String issuerOfferStage(String stage);

  /// Offer channel.
  ///
  /// In ar, this message translates to:
  /// **'{channel, select, web{الويب} ios{iOS} android{أندرويد} api{واجهة برمجية} other{غير محدد}}'**
  String issuerOfferChannel(String channel);

  /// Voided offer badge.
  ///
  /// In ar, this message translates to:
  /// **'ملغى'**
  String get issuerOfferVoided;

  /// M48 title.
  ///
  /// In ar, this message translates to:
  /// **'التقييم والترسية'**
  String get issuerAwardTitle;

  /// M48 in closed.
  ///
  /// In ar, this message translates to:
  /// **'تتم الترسية من لوحة التحكم على الويب.'**
  String get issuerAwardOnWeb;

  /// M48 (CD5).
  ///
  /// In ar, this message translates to:
  /// **'إلغاء الترسية متاح في لوحة التحكم على الويب.'**
  String get issuerAwardChangesOnWeb;

  /// Award card title.
  ///
  /// In ar, this message translates to:
  /// **'الترسية'**
  String get issuerAwardWinner;

  /// Award card title (revoked).
  ///
  /// In ar, this message translates to:
  /// **'ترسية ملغاة'**
  String get issuerAwardRevokedTitle;

  /// Revoked award.
  ///
  /// In ar, this message translates to:
  /// **'أُلغيت هذه الترسية في {date}. السبب: {reason}'**
  String issuerAwardRevoked(String date, String reason);

  /// Award card.
  ///
  /// In ar, this message translates to:
  /// **'السجل التجاري'**
  String get issuerAwardCr;

  /// Award card.
  ///
  /// In ar, this message translates to:
  /// **'الرقم الضريبي'**
  String get issuerAwardVat;

  /// Award card.
  ///
  /// In ar, this message translates to:
  /// **'العرض المتصدر عند الترسية'**
  String get issuerAwardLeading;

  /// Award card.
  ///
  /// In ar, this message translates to:
  /// **'الترتيب عند الترسية'**
  String get issuerAwardRank;

  /// Award card.
  ///
  /// In ar, this message translates to:
  /// **'مبرر الترسية'**
  String get issuerAwardJustification;

  /// Award card.
  ///
  /// In ar, this message translates to:
  /// **'رسالة إلى المتنافس الفائز'**
  String get issuerAwardMessage;

  /// Award card.
  ///
  /// In ar, this message translates to:
  /// **'ملاحظات داخلية'**
  String get issuerAwardNotes;

  /// Award card.
  ///
  /// In ar, this message translates to:
  /// **'تمت الترسية بواسطة'**
  String get issuerAwardBy;

  /// Award card.
  ///
  /// In ar, this message translates to:
  /// **'تاريخ الترسية'**
  String get issuerAwardAt;

  /// Award card.
  ///
  /// In ar, this message translates to:
  /// **'وقت استلام العرض'**
  String get issuerAwardOfferAt;

  /// Award card.
  ///
  /// In ar, this message translates to:
  /// **'المزامنة مع نظام تخطيط الموارد'**
  String get issuerAwardErpSync;

  /// erp_sync.status.
  ///
  /// In ar, this message translates to:
  /// **'{status, select, pending{قيد الانتظار} synced{تمت المزامنة} failed{تعذّرت المزامنة} not_required{غير مطلوبة} other{—}}'**
  String issuerAwardErpStatus(String status);

  /// Award card.
  ///
  /// In ar, this message translates to:
  /// **'بصمة السجل'**
  String get issuerAwardLedgerHash;

  /// M48 standings.
  ///
  /// In ar, this message translates to:
  /// **'الترتيب النهائي'**
  String get issuerAwardStandings;

  /// change_ratio_bps (server-signed).
  ///
  /// In ar, this message translates to:
  /// **'التحسّن {value}'**
  String issuerAwardChange(String value);

  /// Result PDF section.
  ///
  /// In ar, this message translates to:
  /// **'تقرير النتائج'**
  String get issuerReportTitle;

  /// Result PDF section.
  ///
  /// In ar, this message translates to:
  /// **'يفتح التقرير في عارض الملفات، ومنه يمكنك مشاركته.'**
  String get issuerReportShareHint;

  /// Result PDF locale.
  ///
  /// In ar, this message translates to:
  /// **'بالعربية'**
  String get issuerReportArabic;

  /// Result PDF locale.
  ///
  /// In ar, this message translates to:
  /// **'بالإنجليزية'**
  String get issuerReportEnglish;

  /// 202 pending.
  ///
  /// In ar, this message translates to:
  /// **'جارٍ إعداد التقرير…'**
  String get issuerReportGenerating;

  /// Report failure.
  ///
  /// In ar, this message translates to:
  /// **'تعذّر تجهيز التقرير. حاول مرة أخرى.'**
  String get issuerReportFailed;

  /// Report still pending after 2 minutes.
  ///
  /// In ar, this message translates to:
  /// **'ما زال التقرير قيد الإعداد. حاول بعد قليل.'**
  String get issuerReportTimeout;

  /// M16/M17: the join deadline as a date.
  ///
  /// In ar, this message translates to:
  /// **'الانضمام قبل {date}'**
  String invitationsJoinBy(String date);

  /// errors.feature_disabled (RELEASE_SCOPE.md §1.5): 404 behind a release-scope flag.
  ///
  /// In ar, this message translates to:
  /// **'هذه الميزة غير متاحة في هذا الإصدار.'**
  String get errorsFeatureDisabled;

  /// errors.feature_disabled_field: a 422 on a field the release scope hides.
  ///
  /// In ar, this message translates to:
  /// **'هذا الخيار غير متاح في هذا الإصدار.'**
  String get errorsFeatureDisabledField;

  /// Title of the screen behind a route whose feature is off in this release.
  ///
  /// In ar, this message translates to:
  /// **'غير متاح في هذا الإصدار'**
  String get commonFeatureUnavailableTitle;

  /// Hint under the message of the feature-unavailable screen.
  ///
  /// In ar, this message translates to:
  /// **'ستتوفر هذه الميزة في إصدار قادم من بافو.'**
  String get commonFeatureUnavailableHint;

  /// Message of the feature-unavailable screen behind /account/invoices in the core release.
  ///
  /// In ar, this message translates to:
  /// **'الفواتير متاحة في لوحة تحكم بافو على الويب.'**
  String get accountInvoicesOnWeb;

  /// M34: heading of the preset tier cards (simple, standard, maximum protection).
  ///
  /// In ar, this message translates to:
  /// **'مستوى القواعد'**
  String get issuerPresetTierTitle;

  /// M34: helper under the tier cards heading.
  ///
  /// In ar, this message translates to:
  /// **'اختر مستوى واحداً، ويشرح بافو أثره بجملة واحدة. يمكنك تعديل التفاصيل لاحقاً من لوحة التحكم على الويب.'**
  String get issuerPresetTierHint;

  /// M34: pill on the standard tier card.
  ///
  /// In ar, this message translates to:
  /// **'موصى به'**
  String get issuerPresetTierRecommended;

  /// M34: heading of the untiered presets (advanced_rules flag).
  ///
  /// In ar, this message translates to:
  /// **'قوالب أخرى'**
  String get issuerPresetTierOther;

  /// M36: heading of the duration quick picks.
  ///
  /// In ar, this message translates to:
  /// **'مدة استقبال العروض'**
  String get issuerScheduleQuickTitle;

  /// M36 quick pick: 60 minutes.
  ///
  /// In ar, this message translates to:
  /// **'ساعة'**
  String get issuerScheduleQuickHour;

  /// M36 quick pick: 3 hours.
  ///
  /// In ar, this message translates to:
  /// **'3 ساعات'**
  String get issuerScheduleQuickHours3;

  /// M36 quick pick: 24 hours.
  ///
  /// In ar, this message translates to:
  /// **'يوم'**
  String get issuerScheduleQuickDay;

  /// M36 quick pick: 72 hours.
  ///
  /// In ar, this message translates to:
  /// **'3 أيام'**
  String get issuerScheduleQuickDays3;

  /// M36 quick pick: 7 days.
  ///
  /// In ar, this message translates to:
  /// **'أسبوع'**
  String get issuerScheduleQuickWeek;

  /// M36 quick pick: pick the closing time yourself.
  ///
  /// In ar, this message translates to:
  /// **'مخصص'**
  String get issuerScheduleQuickCustom;

  /// M36: hint under the quick picks when offers open on publish.
  ///
  /// In ar, this message translates to:
  /// **'تُحتسب المدة من لحظة النشر.'**
  String get issuerScheduleQuickFromPublish;

  /// M36: relative hint under the closing time, e.g. «يُغلق بعد 3 أيام: 12 نوفمبر 2026، 4:00 م بتوقيت الرياض».
  ///
  /// In ar, this message translates to:
  /// **'يُغلق {relative}: {date}'**
  String issuerScheduleRelativeClose(String relative, String date);

  /// Relative phrase for a duration under an hour.
  ///
  /// In ar, this message translates to:
  /// **'{count, plural, =1{بعد دقيقة} =2{بعد دقيقتين} few{بعد {count} دقائق} many{بعد {count} دقيقة} other{بعد {count} دقيقة}}'**
  String issuerScheduleInMinutes(num count);

  /// Relative phrase for a duration under a day.
  ///
  /// In ar, this message translates to:
  /// **'{count, plural, =1{بعد ساعة} =2{بعد ساعتين} few{بعد {count} ساعات} many{بعد {count} ساعة} other{بعد {count} ساعة}}'**
  String issuerScheduleInHours(num count);

  /// Relative phrase for a duration of a day or more.
  ///
  /// In ar, this message translates to:
  /// **'{count, plural, =1{بعد يوم} =2{بعد يومين} few{بعد {count} أيام} many{بعد {count} يوماً} other{بعد {count} يوم}}'**
  String issuerScheduleInDays(num count);

  /// R16 with the actual duration and the bound (FQ2 self-explaining message).
  ///
  /// In ar, this message translates to:
  /// **'{minutes, plural, =1{المدة دقيقة واحدة فقط} =2{المدة دقيقتان فقط} few{المدة {minutes} دقائق فقط} many{المدة {minutes} دقيقة فقط} other{المدة {minutes} دقيقة فقط}}؛ الحد الأدنى {min, plural, =1{دقيقة واحدة} =2{دقيقتان} few{{min} دقائق} many{{min} دقيقة} other{{min} دقيقة}}.'**
  String issuerFieldDurationTooShortDetail(num minutes, num min);

  /// R16 with the actual duration and the bound (FQ2 self-explaining message).
  ///
  /// In ar, this message translates to:
  /// **'{days, plural, =1{المدة يوم واحد} =2{المدة يومان} few{المدة {days} أيام} many{المدة {days} يوماً} other{المدة {days} يوم}}؛ الحد الأقصى {max, plural, =1{يوم واحد} =2{يومان} few{{max} أيام} many{{max} يوماً} other{{max} يوم}}.'**
  String issuerFieldDurationTooLongDetail(num days, num max);

  /// M35: helper with an example under the title field (FQ1).
  ///
  /// In ar, this message translates to:
  /// **'اسم واضح يفهمه المدعوون. مثال: توريد أجهزة حاسب محمول للإدارة العامة'**
  String get issuerFieldTitleHelper;

  /// M35: helper with an example under the description field (FQ1).
  ///
  /// In ar, this message translates to:
  /// **'مطلوب قبل النشر. اذكر الكمية والمواصفات ومكان التسليم وشروط الدفع.'**
  String get issuerFieldDescriptionExample;

  /// Placeholder of money fields (FQ1); Western digits, two decimals.
  ///
  /// In ar, this message translates to:
  /// **'مثال: 125,000.00'**
  String get commonAmountExample;

  /// M34: notice when the unsaved local draft is restored (FQ6).
  ///
  /// In ar, this message translates to:
  /// **'استُعيد ما أدخلته سابقاً في هذه المنافسة.'**
  String get issuerCreateDraftRestored;

  /// M34: clears the restored local draft.
  ///
  /// In ar, this message translates to:
  /// **'ابدأ من جديد'**
  String get issuerCreateStartOver;

  /// Heading of the error summary shown after a failed submit (FQ8).
  ///
  /// In ar, this message translates to:
  /// **'{count, plural, =1{يرجى تصحيح الحقل التالي:} =2{يرجى تصحيح الحقلين التاليين:} few{يرجى تصحيح الحقول التالية ({count}):} many{يرجى تصحيح الحقول التالية ({count}):} other{يرجى تصحيح الحقول التالية ({count}):}}'**
  String commonErrorSummaryTitle(num count);

  /// Error summary: the field is on another step.
  ///
  /// In ar, this message translates to:
  /// **'في الخطوة {step}'**
  String commonErrorSummaryOtherStep(int step);

  /// Message of the feature-unavailable screen behind /account/organization in the core release (RELEASE_SCOPE.md §4.1).
  ///
  /// In ar, this message translates to:
  /// **'تُدار بيانات المنشأة من لوحة تحكم بافو على الويب.'**
  String get accountOrganizationOnWeb;

  /// M38 documents section and the feature-unavailable screen behind /competitions/:id/attachments in the core release (RELEASE_SCOPE.md §4.1).
  ///
  /// In ar, this message translates to:
  /// **'تُرفع المستندات وتُدار من لوحة تحكم بافو على الويب.'**
  String get issuerDocumentsOnWeb;

  /// M38: the web-only notice when extend and the BAFO round are not in this release (RELEASE_SCOPE.md §4).
  ///
  /// In ar, this message translates to:
  /// **'الترسية وإلغاؤها والإغلاق دون ترسية متاحة في لوحة التحكم على الويب.'**
  String get issuerWebOnlyAwardActions;
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['ar', 'en'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'ar':
      return AppLocalizationsAr();
    case 'en':
      return AppLocalizationsEn();
  }

  throw FlutterError(
    'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}

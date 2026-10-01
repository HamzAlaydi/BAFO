// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Arabic (`ar`).
class AppLocalizationsAr extends AppLocalizations {
  AppLocalizationsAr([String locale = 'ar']) : super(locale);

  @override
  String get appName => 'بافو';

  @override
  String get appTagline => 'أفضل عرض نهائي';

  @override
  String get navHome => 'الرئيسية';

  @override
  String get navCompetitions => 'مشاركاتي';

  @override
  String get navMyCompetitions => 'منافساتي';

  @override
  String get navMyCompetitionsTab => 'منافساتي';

  @override
  String get navNotifications => 'الإشعارات';

  @override
  String get navAccount => 'الحساب';

  @override
  String get commonLanguageArabic => 'العربية';

  @override
  String get commonLanguageEnglish => 'English';

  @override
  String get commonLanguageSwitch => 'تغيير اللغة';

  @override
  String get authLoginTitle => 'تسجيل الدخول';

  @override
  String get authLoginSubtitle => 'سجّل الدخول إلى حساب منشأتك في بافو.';

  @override
  String get authFieldsEmailLabel => 'البريد الإلكتروني';

  @override
  String get authFieldsEmailHint => 'name@company.sa';

  @override
  String get authFieldsPasswordLabel => 'كلمة المرور';

  @override
  String get commonPasswordShow => 'إظهار كلمة المرور';

  @override
  String get commonPasswordHide => 'إخفاء كلمة المرور';

  @override
  String get authLoginSubmit => 'تسجيل الدخول';

  @override
  String get validationRequired => 'هذا الحقل مطلوب.';

  @override
  String get validationEmail => 'أدخل بريداً إلكترونياً صحيحاً.';

  @override
  String get competitionsParticipatingEmptyTitle => 'لا توجد دعوات حتى الآن';

  @override
  String get competitionsParticipatingEmptyMessage =>
      'عندما يدعوك طارح منافسة للمشاركة ستظهر الدعوة هنا.';

  @override
  String get notificationsEmptyTitle => 'لا توجد إشعارات';

  @override
  String get notificationsEmptyMessage =>
      'ستظهر هنا تحديثات المنافسات والعروض.';

  @override
  String get profileLanguageTitle => 'لغة التطبيق';

  @override
  String get authLogoutAction => 'تسجيل الخروج';

  @override
  String get authLogoutConfirmTitle => 'تسجيل الخروج؟';

  @override
  String get authLogoutConfirmMessage =>
      'ستحتاج إلى تسجيل الدخول مرة أخرى للوصول إلى حسابك.';

  @override
  String get commonActionsRetry => 'إعادة المحاولة';

  @override
  String get commonActionsCancel => 'إلغاء';

  @override
  String get commonActionsConfirm => 'تأكيد';

  @override
  String get commonActionsClose => 'إغلاق';

  @override
  String get commonLoading => 'جارٍ التحميل';

  @override
  String get commonErrorTitle => 'تعذّر إكمال الطلب';

  @override
  String get errorsServerError => 'حدث خطأ غير متوقع. حاول مرة أخرى.';

  @override
  String get errorsNetworkError =>
      'تعذّر الاتصال بالخادم. تحقّق من اتصالك بالإنترنت.';

  @override
  String get errorsTimeout =>
      'استغرق الطلب وقتاً أطول من المتوقع. حاول مرة أخرى.';

  @override
  String get errorsUnauthenticated => 'انتهت جلستك. سجّل الدخول مرة أخرى.';

  @override
  String get commonUpdateRequiredTitle => 'يلزم تحديث التطبيق';

  @override
  String get commonUpdateRequiredMessage =>
      'هذا الإصدار من بافو لم يعد مدعوماً. حدّث التطبيق من المتجر للمتابعة.';

  @override
  String get commonMaintenanceTitle => 'بافو تحت الصيانة';

  @override
  String get commonMaintenanceMessage =>
      'نعمل على تحسين الخدمة. حاول مرة أخرى بعد قليل.';

  @override
  String commonCountdownDays(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count يوم',
      many: '$count يوماً',
      few: '$count أيام',
      two: 'يومان',
      one: 'يوم واحد',
    );
    return '$_temp0';
  }

  @override
  String get commonCountdownEnded => 'انتهى الوقت';

  @override
  String commonCountdownRemaining(String time) {
    return 'الوقت المتبقي $time';
  }

  @override
  String navNotificationsUnread(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count إشعار غير مقروء',
      many: '$count إشعاراً غير مقروء',
      few: '$count إشعارات غير مقروءة',
      two: 'إشعاران غير مقروءين',
      one: 'إشعار واحد غير مقروء',
      zero: 'لا توجد إشعارات غير مقروءة',
    );
    return '$_temp0';
  }

  @override
  String get commonActionsNext => 'التالي';

  @override
  String get commonActionsBack => 'السابق';

  @override
  String get commonActionsSkip => 'تخطي';

  @override
  String get commonActionsSave => 'حفظ';

  @override
  String get commonActionsDownload => 'تنزيل';

  @override
  String get commonActionsOpen => 'فتح';

  @override
  String get commonActionsDone => 'تم';

  @override
  String get commonActionsClear => 'مسح';

  @override
  String get commonActionsSearch => 'بحث';

  @override
  String get commonActionsSelect => 'اختيار';

  @override
  String get commonActionsRemove => 'إزالة';

  @override
  String get commonActionsCamera => 'التقاط صورة';

  @override
  String get commonActionsGallery => 'اختيار من الصور';

  @override
  String get commonFieldRequired => 'مطلوب';

  @override
  String get commonFieldOptional => 'اختياري';

  @override
  String commonStepOf(int current, int total) {
    return 'الخطوة $current من $total';
  }

  @override
  String commonPageOf(int current, int total) {
    return 'الصفحة $current من $total';
  }

  @override
  String commonRetryIn(int seconds) {
    return 'أعد المحاولة بعد $seconds ث';
  }

  @override
  String get commonPricesExcludeVat => 'الأسعار لا تشمل ضريبة القيمة المضافة';

  @override
  String get commonOfflineBanner => 'لا يوجد اتصال بالإنترنت.';

  @override
  String get commonOfflineActionsDisabled =>
      'لا يمكن تنفيذ هذا الإجراء دون اتصال بالإنترنت.';

  @override
  String get commonDownloading => 'جارٍ التنزيل';

  @override
  String get commonDownloadFailed => 'تعذّر تنزيل الملف.';

  @override
  String get commonNoAppToOpen => 'لا يوجد تطبيق على جهازك يفتح هذا الملف.';

  @override
  String get commonLinkOpenFailed => 'تعذّر فتح الرابط.';

  @override
  String get commonExternalLink => 'رابط خارجي';

  @override
  String get commonAddendum => 'ملحق';

  @override
  String commonFileSizeBytes(String size) {
    return '$size بايت';
  }

  @override
  String commonFileSizeKb(String size) {
    return '$size كيلوبايت';
  }

  @override
  String commonFileSizeMb(String size) {
    return '$size ميجابايت';
  }

  @override
  String commonTimeRiyadh(String dateTime) {
    return '$dateTime بتوقيت الرياض';
  }

  @override
  String get commonTimeJustNow => 'الآن';

  @override
  String commonTimeMinutesAgo(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'منذ $count دقيقة',
      many: 'منذ $count دقيقة',
      few: 'منذ $count دقائق',
      two: 'منذ دقيقتين',
      one: 'منذ دقيقة',
    );
    return '$_temp0';
  }

  @override
  String commonTimeHoursAgo(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'منذ $count ساعة',
      many: 'منذ $count ساعة',
      few: 'منذ $count ساعات',
      two: 'منذ ساعتين',
      one: 'منذ ساعة',
    );
    return '$_temp0';
  }

  @override
  String commonTimeDaysAgo(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'منذ $count يوم',
      many: 'منذ $count يوماً',
      few: 'منذ $count أيام',
      two: 'منذ يومين',
      one: 'منذ يوم',
    );
    return '$_temp0';
  }

  @override
  String get commonForbiddenTitle => 'لا تملك صلاحية الوصول';

  @override
  String get commonNotFoundTitle => 'غير متاحة';

  @override
  String get commonSupportTitle => 'تواصل مع دعم بافو';

  @override
  String get commonSupportEmail => 'البريد الإلكتروني';

  @override
  String get commonSupportPhone => 'الهاتف';

  @override
  String get commonSupportWhatsapp => 'واتساب';

  @override
  String get commonUpdateAction => 'تحديث التطبيق';

  @override
  String get commonAccountBlockedTitle => 'لا يمكن استخدام الحساب حالياً';

  @override
  String get commonRuleMet => 'متحقق';

  @override
  String get commonRuleNotMet => 'غير متحقق';

  @override
  String commonSelectedCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count عنصر مختار',
      many: '$count عنصراً مختاراً',
      few: '$count عناصر مختارة',
      two: 'عنصران مختاران',
      one: 'عنصر واحد مختار',
      zero: 'لم يُختر شيء',
    );
    return '$_temp0';
  }

  @override
  String get commonDateTimePick => 'اختر التاريخ والوقت';

  @override
  String get validationPhone => 'أدخل رقم جوال سعودي من 9 أرقام يبدأ بالرقم 5.';

  @override
  String get validationCr => 'رقم السجل التجاري 10 أرقام.';

  @override
  String get validationVat => 'الرقم الضريبي 15 رقماً يبدأ وينتهي بالرقم 3.';

  @override
  String get validationPasswordRules => 'كلمة المرور لا تستوفي الشروط.';

  @override
  String get validationPasswordMismatch => 'كلمتا المرور غير متطابقتين.';

  @override
  String get validationUrlHttps => 'أدخل رابطاً يبدأ بـ https://';

  @override
  String validationMaxLength(int max) {
    return 'الحد الأقصى $max حرفاً.';
  }

  @override
  String validationExactDigits(int count) {
    return 'أدخل $count أرقام.';
  }

  @override
  String get validationShortAddress =>
      'العنوان المختصر 4 أحرف إنجليزية كبيرة ثم 4 أرقام، مثل ABCD1234.';

  @override
  String get validationOtp => 'أدخل الرمز المكوّن من 6 أرقام.';

  @override
  String get validationCategoriesMax => 'يمكنك اختيار 20 فئة كحد أقصى.';

  @override
  String get validationAmount => 'أدخل مبلغاً صحيحاً بخانتين عشريتين كحد أقصى.';

  @override
  String get validationAmountPositive => 'يجب أن يكون المبلغ أكبر من صفر.';

  @override
  String get validationAmountWholeRiyals => 'أدخل المبلغ بالريال دون هللات.';

  @override
  String get authWelcomeLanguageTitle => 'اختر لغة التطبيق';

  @override
  String get authWelcomeSlide1Title => 'اطرح منافستك';

  @override
  String get authWelcomeSlide1Body =>
      'أنشئ مناقصة أو مزايدة بقواعد واضحة وجدول زمني محدد.';

  @override
  String get authWelcomeSlide2Title => 'ادعُ المتنافسين';

  @override
  String get authWelcomeSlide2Body =>
      'ادعُ الموردين أو المزايدين بالبريد الإلكتروني أو من الاقتراحات، وتابع انضمامهم.';

  @override
  String get authWelcomeSlide3Title => 'منافسة مباشرة وترسية';

  @override
  String get authWelcomeSlide3Body =>
      'تصل العروض مباشرة ويُعتمد وقتها على خادم بافو، ثم تتم الترسية بوضوح.';

  @override
  String get authWelcomeCreateAccount => 'إنشاء حساب منشأة';

  @override
  String get authLoginForgot => 'نسيت كلمة المرور؟';

  @override
  String get authLoginNoAccount => 'ليس لدى منشأتك حساب؟';

  @override
  String get authLoginCreateAccount => 'أنشئ حساباً';

  @override
  String get authRegisterTitle => 'إنشاء حساب منشأة';

  @override
  String get authRegisterStepAccount => 'بيانات الحساب';

  @override
  String get authRegisterStepCompany => 'بيانات المنشأة';

  @override
  String get authRegisterStepAddress => 'العنوان والموافقة';

  @override
  String get authRegisterSubmit => 'إنشاء الحساب';

  @override
  String get authRegisterHaveAccount => 'لدى منشأتك حساب؟';

  @override
  String get authRegisterSignIn => 'سجّل الدخول';

  @override
  String get authRegisterFixErrors =>
      'راجع الحقول المشار إليها ثم أعد المحاولة.';

  @override
  String get authRegisterAcceptTerms => 'أوافق على الشروط والأحكام';

  @override
  String get authRegisterAcceptPrivacy => 'أوافق على سياسة الخصوصية';

  @override
  String get authRegisterConsentRequired => 'يلزم الموافقة للمتابعة.';

  @override
  String get authRegisterReadDocument => 'قراءة';

  @override
  String get authFieldsNameLabel => 'الاسم الكامل';

  @override
  String get authFieldsPhoneLabel => 'رقم الجوال';

  @override
  String get authFieldsPhoneHint => '5XXXXXXXX';

  @override
  String get authFieldsPasswordConfirmLabel => 'تأكيد كلمة المرور';

  @override
  String get authPasswordRulesTitle => 'يجب أن تحتوي كلمة المرور على:';

  @override
  String get authPasswordRuleLength => '8 أحرف على الأقل';

  @override
  String get authPasswordRuleLower => 'حرف إنجليزي صغير';

  @override
  String get authPasswordRuleUpper => 'حرف إنجليزي كبير';

  @override
  String get authPasswordRuleDigit => 'رقم واحد على الأقل';

  @override
  String get authPasswordRuleSymbol => 'رمز خاص مثل @ أو #';

  @override
  String get organizationFieldsNameLabel => 'اسم المنشأة';

  @override
  String get organizationFieldsCrLabel => 'رقم السجل التجاري';

  @override
  String get organizationFieldsCrHelper => '10 أرقام';

  @override
  String get organizationFieldsRegionLabel => 'المنطقة';

  @override
  String get organizationFieldsCityLabel => 'المدينة';

  @override
  String get organizationFieldsVatRegisteredLabel =>
      'المنشأة مسجّلة في ضريبة القيمة المضافة';

  @override
  String get organizationFieldsVatLabel => 'الرقم الضريبي';

  @override
  String get organizationFieldsVatHelper => '15 رقماً يبدأ وينتهي بالرقم 3';

  @override
  String get organizationFieldsLegalNameArLabel => 'الاسم القانوني بالعربية';

  @override
  String get organizationFieldsLegalNameEnLabel => 'الاسم القانوني بالإنجليزية';

  @override
  String get organizationFieldsWebsiteLabel => 'الموقع الإلكتروني';

  @override
  String get organizationFieldsWebsiteHint => 'https://example.sa';

  @override
  String get organizationFieldsCategoriesLabel => 'الفئات';

  @override
  String get organizationFieldsCategoriesHelper =>
      'اختر حتى 20 فئة تعمل فيها منشأتك.';

  @override
  String get organizationFieldsVisibleInSuggestions =>
      'أظهر منشأتي في اقتراحات طارحي المنافسات';

  @override
  String get organizationAddressTitle => 'العنوان الوطني';

  @override
  String get organizationAddressHelper =>
      'اختياري الآن، ويلزم لاحقاً لإصدار الفواتير.';

  @override
  String get organizationAddressBuildingNumber => 'رقم المبنى';

  @override
  String get organizationAddressStreet => 'الشارع';

  @override
  String get organizationAddressDistrict => 'الحي';

  @override
  String get organizationAddressPostalCode => 'الرمز البريدي';

  @override
  String get organizationAddressAdditionalNumber => 'الرقم الإضافي';

  @override
  String get organizationAddressShortAddress => 'العنوان المختصر';

  @override
  String get authVerifyTitle => 'تأكيد البريد الإلكتروني';

  @override
  String authVerifyMessage(String email) {
    return 'أرسلنا رمز تحقق من 6 أرقام إلى $email.';
  }

  @override
  String get authVerifySubmit => 'تأكيد';

  @override
  String get authOtpLabel => 'رمز التحقق';

  @override
  String authOtpExpiresIn(String time) {
    return 'تنتهي صلاحية الرمز خلال $time';
  }

  @override
  String get authOtpExpired => 'انتهت صلاحية الرمز. اطلب رمزاً جديداً.';

  @override
  String get authOtpResend => 'إعادة إرسال الرمز';

  @override
  String authOtpResendIn(int seconds) {
    return 'إعادة الإرسال بعد $seconds ث';
  }

  @override
  String get authOtpResent => 'أرسلنا رمزاً جديداً.';

  @override
  String get authForgotTitle => 'استعادة كلمة المرور';

  @override
  String get authForgotMessage =>
      'أدخل البريد الإلكتروني المسجّل وسنرسل إليك رمز تحقق.';

  @override
  String get authForgotSubmit => 'إرسال الرمز';

  @override
  String get authForgotSent =>
      'إذا كان البريد مسجلاً فستصلك رسالة برمز التحقق.';

  @override
  String get authResetTitle => 'تعيين كلمة مرور جديدة';

  @override
  String authResetMessage(String email) {
    return 'أدخل الرمز المرسل إلى $email ثم اختر كلمة مرور جديدة.';
  }

  @override
  String get authResetCodeValid => 'الرمز صحيح.';

  @override
  String get authResetNewPasswordLabel => 'كلمة المرور الجديدة';

  @override
  String get authResetSubmit => 'حفظ كلمة المرور';

  @override
  String get authResetDone => 'تم تغيير كلمة المرور. سجّل الدخول.';

  @override
  String get legalLinksTerms => 'الشروط والأحكام';

  @override
  String get legalLinksPrivacy => 'سياسة الخصوصية';

  @override
  String get legalLinksCompetitionRules => 'قواعد المنافسات';

  @override
  String legalVersion(String version) {
    return 'الإصدار $version';
  }

  @override
  String legalPublishedOn(String date) {
    return 'نُشر في $date';
  }

  @override
  String get legalUnavailable => 'هذه الوثيقة غير متاحة حالياً.';

  @override
  String competitionsDirectionLabel(String direction) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'مناقصة',
      'auction': 'مزايدة',
      'other': 'منافسة',
    });
    return '$_temp0';
  }

  @override
  String competitionsDirectionRule(String direction) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'الأقل سعراً يفوز',
      'auction': 'الأعلى سعراً يفوز',
      'other': 'منافسة',
    });
    return '$_temp0';
  }

  @override
  String get competitionsFormatLive => 'مباشرة';

  @override
  String get competitionsFormatSealed => 'بظرف مغلق';

  @override
  String get competitionsStatusDraft => 'مسودة';

  @override
  String get competitionsStatusScheduled => 'مجدولة';

  @override
  String get competitionsStatusLive => 'مفتوحة للعروض';

  @override
  String get competitionsStatusFinalWindow => 'فترة التسعير النهائية';

  @override
  String get competitionsStatusLiveSealed => 'مفتوحة · عروض مغلقة';

  @override
  String get competitionsStatusClosed => 'قيد التقييم';

  @override
  String get competitionsStatusBafoRound => 'جولة العرض النهائي';

  @override
  String get competitionsStatusAwarded => 'تمت الترسية';

  @override
  String get competitionsStatusNotAwarded => 'أُغلقت دون ترسية';

  @override
  String get competitionsStatusCancelled => 'ملغاة';

  @override
  String get competitionsStatusUnknown => 'حالة غير معروفة';

  @override
  String get competitionsStatusClosingSoon => 'تُغلق قريباً';

  @override
  String get competitionsStatusExtended => 'مُدّد الإغلاق';

  @override
  String get competitionsCountdownOpensIn => 'تبدأ خلال';

  @override
  String get competitionsCountdownClosesIn => 'تُغلق خلال';

  @override
  String get competitionsCountdownJoinBefore => 'الانضمام قبل';

  @override
  String get competitionsCountdownBafoEndsIn => 'تنتهي جولة العرض النهائي خلال';

  @override
  String get competitionsCountdownClosing => 'جارٍ الإغلاق…';

  @override
  String competitionsCountdownAnnounceMinutes(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'تبقّت $count دقيقة على الإغلاق',
      many: 'تبقّت $count دقيقة على الإغلاق',
      few: 'تبقّت $count دقائق على الإغلاق',
      two: 'تبقّت دقيقتان على الإغلاق',
      one: 'تبقّت دقيقة واحدة على الإغلاق',
    );
    return '$_temp0';
  }

  @override
  String competitionsExtensionBanner(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'مُدّد وقت الإغلاق $count مرة.',
      many: 'مُدّد وقت الإغلاق $count مرة.',
      few: 'مُدّد وقت الإغلاق $count مرات.',
      two: 'مُدّد وقت الإغلاق مرتين.',
      one: 'مُدّد وقت الإغلاق مرة واحدة.',
    );
    return '$_temp0';
  }

  @override
  String competitionsLatestPossibleClose(String time) {
    return 'أقصى موعد للإغلاق: $time';
  }

  @override
  String get rulesSummaryTitle => 'قواعد المنافسة';

  @override
  String get liveStatusLeading => 'عرضك هو العرض المتصدر';

  @override
  String liveStatusNotLeading(String direction) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'عرضك ليس العرض المتصدر. خفّض عرضك لتنافس.',
      'auction': 'عرضك ليس العرض المتصدر. ارفع عرضك لتنافس.',
      'other': 'عرضك ليس العرض المتصدر.',
    });
    return '$_temp0';
  }

  @override
  String liveStatusRank(int rank, int count) {
    return 'ترتيبك $rank من $count';
  }

  @override
  String get liveStatusHidden => 'استُلم عرضك. لا تُظهر هذه المنافسة الترتيب.';

  @override
  String get liveStatusNoOffer => 'لم تقدّم عرضاً بعد.';

  @override
  String get liveBadgeLeading => 'متصدر';

  @override
  String get liveBadgeNotLeading => 'غير متصدر';

  @override
  String get invitationsAccessJoinRequired => 'بانتظار انضمامك';

  @override
  String get invitationsAccessPlanRequired => 'تتطلب باقة';

  @override
  String get invitationsAccessFull => 'منضم';

  @override
  String get invitationsAccessReadOnly => 'للاطلاع فقط';

  @override
  String get invitationsAccessUnavailable => 'غير متاحة';

  @override
  String get sponsorshipBadgeFeesCovered => 'رسوم مغطّاة';

  @override
  String sponsorshipCoveredBy(String issuer) {
    return 'تغطي $issuer رسوم مشاركتكم في هذه المنافسة.';
  }

  @override
  String get sponsorshipManagedOnWeb =>
      'تُدفع رسوم المشاركة لهذه المنافسة من لوحة تحكم بافو على الويب. حُفظت المسودة.';

  @override
  String get awardOutcomeWon => 'تمت الترسية عليكم';

  @override
  String get awardOutcomeNotSelected => 'لم يتم اختياركم';

  @override
  String get awardOutcomeNotAwarded => 'أُغلقت دون ترسية';

  @override
  String get competitionsDetailTitle => 'تفاصيل المنافسة';

  @override
  String get competitionsDetailIssuer => 'طارح المنافسة';

  @override
  String get competitionsDetailCategory => 'الفئة';

  @override
  String get competitionsDetailRegion => 'المنطقة';

  @override
  String get competitionsDetailReference => 'الرقم المرجعي';

  @override
  String get competitionsDetailDescription => 'الوصف';

  @override
  String get competitionsDetailSchedule => 'المواعيد';

  @override
  String get competitionsDetailDocuments => 'المستندات';

  @override
  String get competitionsDetailNoDocuments => 'لا توجد مستندات.';

  @override
  String get competitionsDetailNotFound =>
      'هذه المنافسة غير موجودة أو ليست لديك صلاحية الوصول إليها.';

  @override
  String competitionsDetailCancelled(String reason) {
    return 'سبب الإلغاء: $reason';
  }

  @override
  String competitionsDetailNotAwarded(String reason) {
    return 'سبب الإغلاق دون ترسية: $reason';
  }

  @override
  String get competitionsScheduleOpensAt => 'بدء استقبال العروض';

  @override
  String get competitionsScheduleClosesAt => 'موعد الإغلاق';

  @override
  String get competitionsScheduleJoinDeadline => 'آخر موعد للانضمام';

  @override
  String get competitionsScheduleFinalWindow => 'بدء فترة التسعير النهائية';

  @override
  String get competitionsScheduleOpensOnPublish => 'عند النشر';

  @override
  String get billingManagedOnWeb =>
      'تُدار الاشتراكات والمدفوعات من لوحة تحكم بافو على الويب.';

  @override
  String get billingInvoicesOnWeb =>
      'الفواتير متاحة في لوحة تحكم بافو على الويب.';

  @override
  String get billingStatusTitle => 'الباقة والاشتراك';

  @override
  String get billingStatusPlan => 'الباقة';

  @override
  String get billingStatusSource => 'نوع الاشتراك';

  @override
  String get billingStatusState => 'الحالة';

  @override
  String get billingStatusEnds => 'ينتهي في';

  @override
  String get billingStatusSeats => 'المقاعد';

  @override
  String billingStatusSeatsValue(int used, int total) {
    return '$used من $total';
  }

  @override
  String billingStatusDaysLeft(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count يوم متبقٍ',
      many: '$count يوماً متبقياً',
      few: '$count أيام متبقية',
      two: 'يومان متبقيان',
      one: 'يوم واحد متبقٍ',
      zero: 'ينتهي اليوم',
    );
    return '$_temp0';
  }

  @override
  String get billingStatusNoPlan => 'لا توجد باقة فعّالة لمنشأتكم.';

  @override
  String get billingStatusUpcoming => 'الاشتراك التالي';

  @override
  String get billingSourcePaid => 'اشتراك مدفوع';

  @override
  String get billingSourceTrial => 'تجربة مجانية';

  @override
  String get billingSourceGrant => 'مقدّم من بافو';

  @override
  String get billingSubscriptionActive => 'فعّال';

  @override
  String get billingSubscriptionPendingPayment => 'بانتظار الدفع';

  @override
  String get billingSubscriptionExpired => 'منتهٍ';

  @override
  String get billingSubscriptionSuperseded => 'استُبدل';

  @override
  String get billingSubscriptionCancelled => 'ملغى';

  @override
  String get billingSubscriptionUnknown => 'غير معروف';

  @override
  String get errorsBadRequest => 'تعذّر فهم الطلب.';

  @override
  String get errorsForbidden =>
      'ليست لديك صلاحية لهذه الصفحة. تواصل مع مالك الحساب.';

  @override
  String get errorsNotFound =>
      'العنصر المطلوب غير موجود أو ليست لديك صلاحية الوصول إليه.';

  @override
  String get errorsConflict =>
      'تعذّر تنفيذ الطلب بسبب تعارض. حدّث الصفحة وحاول مرة أخرى.';

  @override
  String get errorsPayloadTooLarge => 'حجم الطلب أكبر من المسموح.';

  @override
  String get errorsUnsupportedMediaType => 'نوع المحتوى غير مدعوم.';

  @override
  String get errorsValidationFailed => 'راجع البيانات المُدخلة.';

  @override
  String get errorsAppVersionUnsupported =>
      'هذا الإصدار من التطبيق لم يعد مدعوماً. حدّث التطبيق للمتابعة.';

  @override
  String get errorsTooManyRequests => 'طلبات كثيرة. حاول مرة أخرى بعد قليل.';

  @override
  String get errorsServiceUnavailable =>
      'الخدمة غير متاحة مؤقتاً. حاول مرة أخرى بعد قليل.';

  @override
  String get errorsMaintenance => 'بافو تحت الصيانة. حاول مرة أخرى بعد قليل.';

  @override
  String get errorsIdempotencyKeyRequired =>
      'تعذّر إرسال الطلب. حاول مرة أخرى.';

  @override
  String get errorsIdempotencyKeyReused =>
      'تعذّر تأكيد الطلب. أكّد العملية من جديد.';

  @override
  String get errorsIdempotencyRequestInProgress =>
      'ما زال طلبك السابق قيد المعالجة. انتظر قليلاً.';

  @override
  String get errorsInvalidStateTransition =>
      'لم يعد هذا الإجراء متاحاً في الحالة الحالية.';

  @override
  String get errorsFileTypeNotAllowed => 'نوع الملف غير مسموح.';

  @override
  String get errorsFileTooLarge => 'حجم الملف أكبر من المسموح.';

  @override
  String get errorsBadResponse => 'وصل رد غير متوقع من الخادم. حاول مرة أخرى.';

  @override
  String get errorsOffline => 'لا يوجد اتصال بالإنترنت.';

  @override
  String get errorsInvalidCredentials =>
      'البريد الإلكتروني أو كلمة المرور غير صحيحة.';

  @override
  String get errorsEmailNotVerified =>
      'أكّد بريدك الإلكتروني للمتابعة. أرسلنا إليك رمز تحقق.';

  @override
  String get errorsAccountInactive =>
      'حسابك غير مفعّل في هذه المنشأة. تواصل مع مالك الحساب.';

  @override
  String get errorsOrganizationSuspended =>
      'أُوقف حساب منشأتكم مؤقتاً. تواصل مع دعم بافو.';

  @override
  String get errorsOtpInvalid => 'الرمز غير صحيح.';

  @override
  String get errorsOtpExpired => 'انتهت صلاحية الرمز. اطلب رمزاً جديداً.';

  @override
  String get errorsOtpTooManyAttempts =>
      'أُبطل الرمز بعد محاولات كثيرة. اطلب رمزاً جديداً.';

  @override
  String get errorsOtpResendCooldown => 'انتظر قليلاً قبل طلب رمز جديد.';

  @override
  String get errorsPasswordIncorrect => 'كلمة المرور الحالية غير صحيحة.';

  @override
  String get errorsTeamInvitationInvalid =>
      'رابط الدعوة غير صالح أو منتهٍ. اطلب من مدير الحساب إعادة الإرسال.';

  @override
  String get errorsSeatLimitReached => 'اكتملت مقاعد باقتكم.';

  @override
  String get errorsCannotModifyOwner => 'لا يمكن تعديل مالك الحساب أو إزالته.';

  @override
  String get errorsCannotModifySelf =>
      'لا يمكنك تعديل عضويتك أو إزالتها بنفسك.';

  @override
  String get errorsAccountDeletionBlocked =>
      'لا يمكن حذف الحساب قبل انتهاء المنافسات والمشاركات المفتوحة.';

  @override
  String get errorsAccountDeletionPending => 'يوجد طلب حذف قيد المعالجة.';

  @override
  String get errorsInvitationEmailMismatch =>
      'يجب التسجيل بالبريد الإلكتروني المدعو.';

  @override
  String get errorsCompetitionNotEditable =>
      'لا يمكن تعديل هذه البيانات في حالة المنافسة الحالية.';

  @override
  String get errorsIssuerPlanRequired =>
      'تحتاج منشأتكم إلى باقة فعّالة لطرح المنافسات.';

  @override
  String get errorsAuctionNotEnabled =>
      'المزايدات غير مفعّلة لمنشأتكم. تواصل مع بافو.';

  @override
  String get errorsMinParticipantsNotMet =>
      'عدد المدعوين أقل من الحد الأدنى للمتنافسين.';

  @override
  String get errorsMaxParticipantsExceeded =>
      'تجاوزت الحد الأقصى لعدد المتنافسين.';

  @override
  String get errorsLiveEventCapacityReached =>
      'بلغ عدد المنافسات المباشرة في هذا الوقت الحد الأقصى. اختر وقتاً آخر.';

  @override
  String get errorsExtendInvalid => 'موعد الإغلاق الجديد غير مسموح.';

  @override
  String get errorsInvitationCutoffPassed =>
      'انتهى موعد إرسال الدعوات لهذه المنافسة.';

  @override
  String get errorsInvitationInvalid => 'رابط الدعوة غير صالح أو منتهٍ.';

  @override
  String get errorsInvitationBelongsToAnotherOrganization =>
      'هذه الدعوة مرتبطة بمنشأة أخرى.';

  @override
  String get errorsJoinDeadlinePassed =>
      'انتهى موعد الانضمام إلى هذه المنافسة.';

  @override
  String get errorsAlreadyParticipating =>
      'منشأتكم منضمة إلى هذه المنافسة بالفعل.';

  @override
  String get errorsTermsNotAccepted => 'يلزم قبول قواعد المنافسة للانضمام.';

  @override
  String get errorsNotAParticipant =>
      'هذا الإجراء متاح للمنشآت المنضمة إلى المنافسة فقط.';

  @override
  String get errorsCommentsClosed => 'الاستفسارات مغلقة لهذه المنافسة.';

  @override
  String get errorsReportNotAvailable => 'التقرير غير متاح قبل إغلاق المنافسة.';

  @override
  String get errorsOfferAmountInvalid => 'أدخل مبلغاً صحيحاً أكبر من صفر.';

  @override
  String get errorsOfferAmountTooLarge => 'المبلغ أكبر من الحد المسموح.';

  @override
  String get errorsOfferGranularity =>
      'المبلغ لا يطابق دقة المبالغ في هذه المنافسة.';

  @override
  String get errorsOfferNotAccepting => 'لا تستقبل المنافسة العروض الآن.';

  @override
  String get errorsOfferClosed => 'أُغلق استقبال العروض.';

  @override
  String get errorsOfferNotShortlisted =>
      'منشأتكم ليست ضمن القائمة المختصرة لجولة العرض النهائي.';

  @override
  String get errorsOfferBafoAlreadySubmitted => 'قدّمتم عرضكم النهائي بالفعل.';

  @override
  String get errorsOfferStartPrice => 'العرض لا يستوفي سعر البداية.';

  @override
  String get errorsOfferStepNotMet => 'العرض لا يستوفي الحد الأدنى للتحسين.';

  @override
  String get errorsOfferBafoWorseThanReference =>
      'لا يمكن أن يكون عرضك النهائي أسوأ من عرضك الأخير.';

  @override
  String get errorsOfferOutlierConfirmRequired =>
      'يختلف هذا العرض كثيراً عن عرضك الحالي. أكّده للمتابعة.';

  @override
  String get errorsBafoNotEnabled =>
      'جولة العرض النهائي غير مفعّلة لهذه المنافسة.';

  @override
  String get errorsBafoAlreadyUsed =>
      'أُجريت جولة العرض النهائي لهذه المنافسة من قبل.';

  @override
  String get errorsAwardParticipantHasNoOffer =>
      'لا يوجد عرض حالي لهذا المتنافس.';

  @override
  String get errorsAwardJustificationRequired => 'يلزم مبرر لهذه الترسية.';

  @override
  String get errorsAwardReserveConfirmationRequired =>
      'يلزم تأكيد الترسية رغم عدم تحقق السعر المستهدف أو الحد الأدنى المقبول.';

  @override
  String get errorsPlanRequired => 'تحتاج منشأتكم إلى باقة فعّالة للانضمام.';

  @override
  String get errorsPurchaseNotAvailableOnPlatform =>
      'تُدار الاشتراكات والمدفوعات من لوحة تحكم بافو على الويب.';

  @override
  String get errorsBillingProfileIncomplete =>
      'بيانات الفوترة للمنشأة غير مكتملة.';

  @override
  String get errorsSponsorshipNotEnabled =>
      'تغطية رسوم المشاركة غير مفعّلة لمنشأتكم.';

  @override
  String get errorsSponsorshipPaymentRequired =>
      'تُدفع رسوم المشاركة لهذه المنافسة من لوحة تحكم بافو على الويب.';

  @override
  String get errorsInvoicePdfNotReady => 'الفاتورة غير جاهزة بعد.';

  @override
  String get commonNoteLabel => 'ملاحظة';

  @override
  String homeGreeting(String name) {
    return 'مرحباً، $name';
  }

  @override
  String get homeGreetingFallback => 'مرحباً';

  @override
  String get homeIssuerSectionTitle => 'المنافسات التي تطرحونها';

  @override
  String get homeParticipantSectionTitle => 'مشاركاتكم';

  @override
  String get homeStatsActiveCompetitions => 'منافسات نشطة';

  @override
  String get homeStatsDraftCompetitions => 'مسودات';

  @override
  String get homeStatsLiveNow => 'مفتوحة للعروض الآن';

  @override
  String get homeStatsAwaitingAward => 'بانتظار الترسية';

  @override
  String get homeStatsOffersReceived30d => 'عروض مستلمة خلال 30 يوماً';

  @override
  String get homeStatsPendingInvitations => 'دعوات بانتظار ردّكم';

  @override
  String get homeStatsActiveParticipations => 'مشاركات نشطة';

  @override
  String get homeStatsOffersSubmitted30d => 'عروض مقدّمة خلال 30 يوماً';

  @override
  String get homeStatsAwardsWon => 'ترسيات لصالحكم';

  @override
  String get homeQuickActionsTitle => 'إجراءات سريعة';

  @override
  String get homeActionCreateCompetition => 'طرح منافسة جديدة';

  @override
  String get homeActionParticipating => 'استعراض مشاركاتي';

  @override
  String get homeActionMyCompetitions => 'استعراض منافساتي';

  @override
  String get homeCreateNeedsPlan => 'تحتاج إلى باقة فعّالة لطرح المنافسات.';

  @override
  String get homeSubscriptionTitle => 'الباقة';

  @override
  String homeSubscriptionEnds(String date) {
    return 'ينتهي في $date';
  }

  @override
  String get homeSubscriptionOpen => 'تفاصيل الباقة';

  @override
  String homeAlertSubscriptionExpiring(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'ينتهي اشتراك منشأتكم خلال $count يوم.',
      many: 'ينتهي اشتراك منشأتكم خلال $count يوماً.',
      few: 'ينتهي اشتراك منشأتكم خلال $count أيام.',
      two: 'ينتهي اشتراك منشأتكم خلال يومين.',
      one: 'ينتهي اشتراك منشأتكم غداً.',
      zero: 'ينتهي اشتراك منشأتكم اليوم.',
    );
    return '$_temp0';
  }

  @override
  String get homeAlertSubscriptionExpiringSoon =>
      'ينتهي اشتراك منشأتكم قريباً.';

  @override
  String get homeAlertSubscriptionExpired => 'انتهى اشتراك منشأتكم.';

  @override
  String get homeAlertPlanRequired =>
      'تحتاج منشأتكم إلى باقة فعّالة لطرح المنافسات والانضمام إليها.';

  @override
  String get homeAlertTrialAvailable => 'تتوفر لمنشأتكم فترة تجريبية مجانية.';

  @override
  String get homeAlertBillingProfileIncomplete =>
      'بيانات الفوترة لمنشأتكم غير مكتملة.';

  @override
  String get homeAlertBillingProfileAction => 'أكملوها من صفحة بيانات المنشأة.';

  @override
  String get homeActivityTitle => 'آخر النشاطات';

  @override
  String get homeActivityEmpty => 'لا توجد نشاطات بعد.';

  @override
  String homeActivityCompetitionCreated(String title) {
    return 'أُنشئت مسودة «$title».';
  }

  @override
  String homeActivityCompetitionPublished(String title) {
    return 'نُشرت «$title».';
  }

  @override
  String homeActivityCompetitionCancelled(String title) {
    return 'أُلغيت «$title».';
  }

  @override
  String homeActivityCompetitionClosed(String title) {
    return 'أُغلق استقبال العروض في «$title».';
  }

  @override
  String homeActivityAwardIssued(String title) {
    return 'تمت ترسية «$title».';
  }

  @override
  String homeActivityInvitationJoined(String title) {
    return 'انضممتم إلى «$title».';
  }

  @override
  String get homeActivityMemberAdded => 'أُضيف عضو إلى الفريق.';

  @override
  String get homeActivitySubscriptionActivated => 'فُعِّل اشتراك منشأتكم.';

  @override
  String get homeActivityOther => 'تحديث على حساب المنشأة.';

  @override
  String get homeActivityActorSystem => 'النظام';

  @override
  String get homeRefreshFailed =>
      'تعذّر تحديث البيانات، وتظهر آخر بيانات محمّلة.';

  @override
  String get notificationsFilterAll => 'الكل';

  @override
  String get notificationsFilterUnread => 'غير المقروءة';

  @override
  String get notificationsUnreadEmptyTitle => 'لا توجد إشعارات غير مقروءة';

  @override
  String get notificationsUnreadEmptyMessage => 'اطّلعتم على جميع إشعاراتكم.';

  @override
  String get notificationsMarkAllRead => 'تعليم الكل كمقروء';

  @override
  String get notificationsMarkRead => 'تعليم كمقروء';

  @override
  String get notificationsDelete => 'حذف الإشعار';

  @override
  String get notificationsDeleteAll => 'حذف كل الإشعارات';

  @override
  String get notificationsDeleteAllConfirmTitle => 'حذف كل الإشعارات؟';

  @override
  String get notificationsDeleteAllConfirmMessage =>
      'ستُحذف جميع إشعاراتكم نهائياً.';

  @override
  String get notificationsDeleted => 'حُذف الإشعار.';

  @override
  String get notificationsAllMarkedRead => 'عُلّمت جميع الإشعارات كمقروءة.';

  @override
  String get notificationsAllDeleted => 'حُذفت جميع الإشعارات.';

  @override
  String get notificationsUnreadLabel => 'غير مقروء';

  @override
  String get notificationsMoreActions => 'إجراءات إضافية';

  @override
  String get notificationsLoadMoreFailed => 'تعذّر تحميل المزيد.';

  @override
  String get notificationsPushTitle => 'فعّلوا التنبيهات الفورية';

  @override
  String get notificationsPushBody =>
      'نرسل إليكم تنبيهاً عند وصول دعوة جديدة، وعند بدء استقبال العروض واقتراب الإغلاق، وعند صدور النتيجة. لا تتضمن التنبيهات أي مبالغ.';

  @override
  String get notificationsPushAllow => 'تفعيل التنبيهات';

  @override
  String get notificationsPushNotNow => 'ليس الآن';

  @override
  String get notificationsPushEnabled =>
      'فُعِّلت التنبيهات الفورية على هذا الجهاز.';

  @override
  String get notificationsPushDenied =>
      'التنبيهات الفورية مرفوضة. يمكنكم السماح بها من إعدادات الجهاز.';

  @override
  String get notificationsPushUnavailable =>
      'التنبيهات الفورية غير متاحة على هذا الجهاز حالياً، وتصلكم الإشعارات داخل التطبيق.';

  @override
  String get notificationsPushOff => 'التنبيهات الفورية غير مفعّلة.';

  @override
  String get notificationsPushSettingTitle => 'التنبيهات الفورية';

  @override
  String get notificationsPushTurnOn => 'تفعيل';

  @override
  String get accountSectionAccount => 'حسابي';

  @override
  String get accountSectionOrganization => 'المنشأة';

  @override
  String get accountSectionApp => 'التطبيق';

  @override
  String get accountRoleOwner => 'المالك';

  @override
  String get accountRoleAdmin => 'مدير';

  @override
  String get accountRoleMember => 'عضو';

  @override
  String get accountVerified => 'منشأة موثّقة';

  @override
  String accountVersion(String version) {
    return 'الإصدار $version';
  }

  @override
  String get accountMeUnavailable =>
      'تعذّر تحميل بيانات الحساب. اسحبوا الشاشة للتحديث عند توفر الاتصال.';

  @override
  String get accountSaved => 'حُفظت التغييرات.';

  @override
  String get accountDiscardTitle => 'تجاهل التغييرات؟';

  @override
  String get accountDiscardMessage => 'لم تُحفظ تعديلاتكم بعد.';

  @override
  String get accountDiscardAction => 'تجاهل';

  @override
  String get accountNotSet => 'غير محدد';

  @override
  String get accountImagePick => 'اختيار صورة';

  @override
  String get accountImageRemove => 'إزالة الصورة';

  @override
  String get accountProfileTitle => 'الملف الشخصي';

  @override
  String get accountProfileEmailHelper =>
      'يُستخدم لتسجيل الدخول ولا يمكن تغييره.';

  @override
  String get accountProfileAvatarChange => 'تغيير الصورة الشخصية';

  @override
  String get accountProfileAvatarHint =>
      'PNG أو JPG أو WEBP بحجم أقصاه 2 ميجابايت.';

  @override
  String get accountProfileAvatarUpdated => 'حُدّثت الصورة الشخصية.';

  @override
  String get accountProfileAvatarRemoved => 'أُزيلت الصورة الشخصية.';

  @override
  String get accountPasswordTitle => 'تغيير كلمة المرور';

  @override
  String get accountPasswordCurrent => 'كلمة المرور الحالية';

  @override
  String get accountPasswordNew => 'كلمة المرور الجديدة';

  @override
  String get accountPasswordConfirm => 'تأكيد كلمة المرور الجديدة';

  @override
  String get accountPasswordOtherDevices =>
      'سيُسجَّل خروجكم من الأجهزة الأخرى.';

  @override
  String get accountPasswordChanged =>
      'تم تغيير كلمة المرور، وسُجّل خروجكم من الأجهزة الأخرى.';

  @override
  String get accountOrganizationTitle => 'بيانات المنشأة';

  @override
  String get accountOrganizationEdit => 'تعديل البيانات';

  @override
  String get accountOrganizationReadOnly =>
      'يعدّل مالك الحساب أو مديره بيانات المنشأة.';

  @override
  String get accountOrganizationIdentity => 'الهوية';

  @override
  String get accountOrganizationTax => 'الضريبة';

  @override
  String get accountOrganizationLocation => 'الموقع';

  @override
  String get accountOrganizationContact => 'التواصل';

  @override
  String get accountOrganizationActivity => 'النشاط';

  @override
  String get accountOrganizationEmail => 'البريد الإلكتروني للمنشأة';

  @override
  String get accountOrganizationPhone => 'هاتف المنشأة';

  @override
  String get accountOrganizationCrReadOnly =>
      'لا يمكن تغيير رقم السجل التجاري.';

  @override
  String get accountOrganizationVatRegistered => 'مسجّلة';

  @override
  String get accountOrganizationVatNotRegistered => 'غير مسجّلة';

  @override
  String get accountOrganizationSuggestionsOn =>
      'تظهر في اقتراحات طارحي المنافسات';

  @override
  String get accountOrganizationSuggestionsOff =>
      'لا تظهر في اقتراحات طارحي المنافسات';

  @override
  String get accountOrganizationFeatures => 'الخصائص';

  @override
  String get accountOrganizationFeatureApi => 'الربط البرمجي (API)';

  @override
  String get accountOrganizationFeatureAuction => 'المزايدات';

  @override
  String get accountOrganizationFeatureSponsorship => 'تغطية رسوم المشاركة';

  @override
  String get accountOrganizationFeatureOn => 'مفعّلة';

  @override
  String get accountOrganizationFeatureOff => 'غير مفعّلة';

  @override
  String get accountOrganizationFeaturesNote => 'لتفعيلها تواصلوا مع بافو.';

  @override
  String accountOrganizationBillingIncomplete(String fields) {
    return 'بيانات الفوترة غير مكتملة. المطلوب: $fields.';
  }

  @override
  String get accountOrganizationOtherFields => 'حقول أخرى';

  @override
  String get accountOrganizationComplete => 'إكمال البيانات';

  @override
  String get accountOrganizationLogoChange => 'تغيير الشعار';

  @override
  String get accountOrganizationLogoHint => 'صورة بحجم أقصاه 2 ميجابايت.';

  @override
  String get accountOrganizationLogoUpdated => 'حُدّث الشعار.';

  @override
  String get accountOrganizationLogoRemoved => 'أُزيل الشعار.';

  @override
  String get accountOrganizationProfileDocument => 'ملف تعريف المنشأة';

  @override
  String get accountOrganizationProfileDocumentNone =>
      'لم يُرفع ملف تعريف بعد.';

  @override
  String get accountOrganizationProfileDocumentWeb =>
      'يُرفع ملف التعريف من لوحة تحكم بافو على الويب.';

  @override
  String get accountTeamTitle => 'فريق العمل';

  @override
  String get accountTeamSeats => 'المقاعد المستخدمة';

  @override
  String accountTeamSeatsFull(int used, int total) {
    return 'اكتملت مقاعد باقتكم ($used/$total).';
  }

  @override
  String get accountTeamInvite => 'دعوة عضو';

  @override
  String get accountTeamEmptyTitle => 'لم تضيفوا أعضاء بعد';

  @override
  String get accountTeamEmptyMessage =>
      'ادعوا زملاءكم لإدارة المنافسات والعروض معكم.';

  @override
  String get accountTeamStatusInvited => 'بانتظار قبول الدعوة';

  @override
  String get accountTeamStatusActive => 'نشط';

  @override
  String get accountTeamStatusInactive => 'موقوف';

  @override
  String get accountTeamCanAward => 'صلاحية الترسية';

  @override
  String get accountTeamCanPurchase => 'صلاحية الشراء';

  @override
  String get accountTeamCanAwardHint => 'يستطيع ترسية المنافسات.';

  @override
  String get accountTeamCanPurchaseHint =>
      'يستطيع الشراء من لوحة تحكم بافو على الويب.';

  @override
  String get accountTeamYou => 'أنت';

  @override
  String get accountTeamLocked => 'لا يمكن تعديل هذا العضو.';

  @override
  String accountTeamInvitedOn(String date) {
    return 'دُعي في $date';
  }

  @override
  String accountTeamJoinedOn(String date) {
    return 'انضم في $date';
  }

  @override
  String get accountTeamMemberTitle => 'عضو الفريق';

  @override
  String get accountTeamRole => 'الدور';

  @override
  String get accountTeamRoleAdminHint =>
      'يدير بيانات المنشأة والفريق وجميع المنافسات.';

  @override
  String get accountTeamRoleMemberHint =>
      'يطرح المنافسات ويدير ما أنشأه منها، ويقدّم العروض.';

  @override
  String get accountTeamSendInvite => 'إرسال الدعوة';

  @override
  String accountTeamInviteSent(String email) {
    return 'أُرسلت الدعوة إلى $email.';
  }

  @override
  String get accountTeamResend => 'إعادة إرسال الدعوة';

  @override
  String get accountTeamResent => 'أُعيد إرسال الدعوة.';

  @override
  String get accountTeamDeactivate => 'إيقاف العضو';

  @override
  String get accountTeamDeactivateConfirmMessage =>
      'لن يتمكن العضو من تسجيل الدخول حتى إعادة تفعيله.';

  @override
  String get accountTeamDeactivated => 'أُوقف العضو.';

  @override
  String get accountTeamReactivate => 'إعادة تفعيل العضو';

  @override
  String get accountTeamReactivated => 'أُعيد تفعيل العضو.';

  @override
  String get accountTeamRemove => 'إزالة العضو';

  @override
  String accountTeamRemoveConfirmTitle(String name) {
    return 'إزالة $name من الفريق؟';
  }

  @override
  String get accountTeamRemoveConfirmMessage =>
      'سيفقد العضو الوصول إلى حساب المنشأة، ويبقى سجل ما أنجزه.';

  @override
  String get accountTeamRemoved => 'أُزيل العضو من الفريق.';

  @override
  String get accountTeamMemberNotFound => 'هذا العضو غير موجود في فريقكم.';

  @override
  String get accountInvoicesTitle => 'الفواتير';

  @override
  String get accountInvoicesEmptyTitle => 'لا توجد فواتير بعد';

  @override
  String get accountInvoicesTypeTax => 'فاتورة ضريبية';

  @override
  String get accountInvoicesTypeCredit => 'إشعار دائن';

  @override
  String get accountInvoicesStatusCleared => 'معتمدة';

  @override
  String get accountInvoicesStatusReported => 'مُبلَّغ عنها';

  @override
  String get accountInvoicesStatusPending => 'قيد الإصدار';

  @override
  String get accountInvoicesStatusRejected => 'مرفوضة';

  @override
  String get accountInvoicesStatusFailed => 'تعذّر الإصدار';

  @override
  String accountInvoicesIssued(String date) {
    return 'صدرت في $date';
  }

  @override
  String get accountSettingsTitle => 'الإعدادات';

  @override
  String get accountSettingsAbout => 'حول التطبيق';

  @override
  String get accountSettingsVersion => 'إصدار التطبيق';

  @override
  String get accountSettingsLegal => 'الوثائق القانونية';

  @override
  String get accountSettingsLicenses => 'تراخيص البرمجيات مفتوحة المصدر';

  @override
  String get accountHelpTitle => 'المساعدة والتواصل';

  @override
  String get accountHelpIntro =>
      'نسعد بمساعدتكم. تواصلوا معنا مباشرة أو أرسلوا رسالة.';

  @override
  String get accountHelpNoContacts =>
      'بيانات التواصل غير متاحة حالياً، ويمكنكم مراسلتنا عبر النموذج.';

  @override
  String get accountHelpFormTitle => 'راسلونا';

  @override
  String get accountHelpSubject => 'الموضوع';

  @override
  String get accountHelpMessage => 'الرسالة';

  @override
  String get accountHelpSend => 'إرسال';

  @override
  String get accountHelpSentTitle => 'استلمنا رسالتكم';

  @override
  String get accountHelpSentMessage =>
      'سيتواصل معكم فريق بافو قريباً عبر البريد الإلكتروني.';

  @override
  String get accountHelpSendAnother => 'إرسال رسالة أخرى';

  @override
  String get accountDeleteTitle => 'حذف الحساب';

  @override
  String get accountDeleteScopeOrganization =>
      'سيُحذف حساب المنشأة وجميع أعضائها بعد 14 يوماً.';

  @override
  String get accountDeleteScopeUser =>
      'سيُحذف حسابكم الشخصي فقط بعد 14 يوماً، وتبقى المنشأة وبقية أعضائها.';

  @override
  String get accountDeleteCancelWindow => 'يمكنكم إلغاء الطلب خلال هذه المدة.';

  @override
  String get accountDeleteKeptTitle => 'ما الذي يبقى محفوظاً';

  @override
  String get accountDeleteKept =>
      'نحتفظ بالسجلات النظامية والمالية: الفواتير والمدفوعات والمنافسات وسجل العروض.';

  @override
  String get accountDeleteReason => 'سبب الحذف';

  @override
  String get accountDeleteConfirmTitle => 'حذف الحساب؟';

  @override
  String accountDeletePending(String date) {
    return 'سيُحذف الحساب في $date.';
  }

  @override
  String get accountDeleteCancel => 'إلغاء طلب الحذف';

  @override
  String get accountDeleteCancelled => 'أُلغي طلب الحذف.';

  @override
  String get accountDeleteRequested => 'سُجّل طلب حذف الحساب.';

  @override
  String get accountDeleteBlockedTitle => 'لا يمكن حذف الحساب الآن';

  @override
  String get accountDeleteBlockedMessage =>
      'أنهوا المنافسات والمشاركات المفتوحة التالية أولاً.';

  @override
  String get accountDeleteBlockerIssued => 'منافسة تطرحونها';

  @override
  String get accountDeleteBlockerParticipation => 'مشاركة في منافسة';

  @override
  String get competitionsParticipatingFilterActive => 'النشطة';

  @override
  String get competitionsParticipatingFilterEnded => 'المنتهية';

  @override
  String get competitionsParticipatingFilterAll => 'الكل';

  @override
  String get competitionsParticipatingDirectionAll => 'كل الأنواع';

  @override
  String get competitionsParticipatingSearchHint => 'ابحث في عناوين المنافسات';

  @override
  String get competitionsParticipatingNoResultsTitle => 'لا توجد نتائج';

  @override
  String get competitionsParticipatingNoResultsMessage =>
      'جرّب كلمات بحث أو تصفية أخرى.';

  @override
  String competitionsParticipatingNeedsAction(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count دعوة بانتظار ردّكم',
      many: '$count دعوة بانتظار ردّكم',
      few: '$count دعوات بانتظار ردّكم',
      two: 'دعوتان بانتظار ردّكم',
      one: 'دعوة واحدة بانتظار ردّكم',
    );
    return '$_temp0';
  }

  @override
  String competitionsParticipatingMyOffer(String amount) {
    return 'عرضك: $amount';
  }

  @override
  String get competitionsParticipatingLoadMoreFailed => 'تعذّر تحميل المزيد.';

  @override
  String competitionsParticipatingCardSemantics(String title, String status) {
    return '$title، $status';
  }

  @override
  String get invitationsJoinAction => 'الانضمام';

  @override
  String get invitationsDeclineAction => 'الاعتذار';

  @override
  String get invitationsCardTitle => 'دعوة للمشاركة';

  @override
  String invitationsInvitedBy(String issuer) {
    return 'دعاكم $issuer للمشاركة في هذه المنافسة.';
  }

  @override
  String invitationsSentAt(String date) {
    return 'أُرسلت الدعوة في $date';
  }

  @override
  String get invitationsOwnPlan => 'تنضمون بباقتكم الحالية.';

  @override
  String invitationsSponsoredOnly(String sponsor) {
    return 'تغطي $sponsor رسوم مشاركتكم في هذه المنافسة فقط.';
  }

  @override
  String get invitationsPlanRequiredBody =>
      'تحتاجون إلى باقة فعّالة للانضمام إلى هذه المنافسة.';

  @override
  String get invitationsPlanRequiredMore => 'لماذا؟';

  @override
  String get invitationsUnavailableDeclined =>
      'اعتذرتم عن المشاركة في هذه المنافسة.';

  @override
  String get invitationsUnavailableExpired => 'انتهت صلاحية هذه الدعوة.';

  @override
  String get invitationsUnavailableRevoked => 'ألغى طارح المنافسة هذه الدعوة.';

  @override
  String get invitationsUnavailableDeadline =>
      'انتهى موعد الانضمام إلى هذه المنافسة.';

  @override
  String get invitationsUnavailableClosed =>
      'لم تعد هذه المنافسة تقبل متنافسين جدداً.';

  @override
  String get invitationsDocumentsTitle => 'مستندات الدعوة';

  @override
  String get invitationsJoinTitle => 'الانضمام إلى المنافسة';

  @override
  String get invitationsJoinIntro =>
      'راجعوا قواعد المنافسة قبل الانضمام. بعد الانضمام تصبح منشأتكم متنافساً ويمكنكم تقديم العروض.';

  @override
  String get invitationsJoinAcceptTerms => 'أوافق على شروط المنافسة';

  @override
  String get invitationsJoinTermsRequired =>
      'وافقوا على شروط المنافسة للمتابعة.';

  @override
  String get invitationsJoinConfirm => 'تأكيد الانضمام';

  @override
  String get invitationsJoinSucceeded => 'انضممتم إلى المنافسة';

  @override
  String get invitationsDeclineTitle => 'الاعتذار عن المشاركة';

  @override
  String get invitationsDeclineMessage =>
      'لن تتمكنوا من الانضمام إلى هذه المنافسة بعد الاعتذار.';

  @override
  String get invitationsDeclineReasonLabel => 'سبب الاعتذار';

  @override
  String get invitationsDeclineReasonHelper =>
      'اختياري، ويطّلع عليه طارح المنافسة.';

  @override
  String get invitationsDeclineConfirm => 'تأكيد الاعتذار';

  @override
  String get invitationsDeclineSucceeded => 'أُرسل اعتذاركم إلى طارح المنافسة.';

  @override
  String get invitationsAccessInfoTitle => 'الانضمام يتطلب باقة فعّالة';

  @override
  String get invitationsAccessInfoPlanRequired =>
      'تحتاج منشأتكم إلى باقة فعّالة للانضمام.';

  @override
  String get invitationsAccessInfoUnavailableTitle => 'الانضمام غير متاح';

  @override
  String get invitationsAccessInfoDeclineHint =>
      'يمكنكم الاعتذار عن الدعوة إن لم ترغبوا في المشاركة.';

  @override
  String get competitionsParticipantLiveRoom => 'غرفة العروض';

  @override
  String get competitionsParticipantQa => 'الاستفسارات';

  @override
  String get competitionsParticipantMyOffers => 'عروضي';

  @override
  String get competitionsParticipantStandingTitle => 'موقف عرضكم';

  @override
  String get competitionsParticipantCurrentOffer => 'عرضكم الحالي';

  @override
  String competitionsParticipantAlias(int alias) {
    return 'رقمكم في هذه المنافسة: المتنافس $alias';
  }

  @override
  String get competitionsParticipantVerified => 'منشأة موثّقة';

  @override
  String get competitionsParticipantEvaluation =>
      'أُغلق استقبال العروض، والمنافسة الآن قيد التقييم.';

  @override
  String get competitionsParticipantScheduled =>
      'تبدأ العروض عند موعد البدء، ويمكنكم متابعة الاستفسارات حتى ذلك الحين.';

  @override
  String get competitionsParticipantResultTitle => 'النتيجة';

  @override
  String get competitionsParticipantResultWon =>
      'تمت ترسية هذه المنافسة عليكم.';

  @override
  String competitionsParticipantResultWinningAmount(String amount) {
    return 'قيمة العرض الفائز: $amount';
  }

  @override
  String get competitionsParticipantResultNotSelected =>
      'أُرسيت المنافسة على متنافس آخر.';

  @override
  String get competitionsParticipantResultNotAwarded =>
      'أُغلقت المنافسة دون ترسية.';

  @override
  String get competitionsParticipantResultHidden =>
      'لا تنشر هذه المنافسة نتائجها للمتنافسين.';

  @override
  String get competitionsParticipantResultMessage => 'رسالة طارح المنافسة';

  @override
  String get liveRoomTitle => 'غرفة العروض';

  @override
  String liveLeadingAmount(String amount) {
    return 'العرض المتصدر: $amount';
  }

  @override
  String liveExtended(int minutes) {
    String _temp0 = intl.Intl.pluralLogic(
      minutes,
      locale: localeName,
      other: 'مُدّد وقت الإغلاق $minutes دقيقة بسبب عرض في الدقائق الأخيرة.',
      many: 'مُدّد وقت الإغلاق $minutes دقيقة بسبب عرض في الدقائق الأخيرة.',
      few: 'مُدّد وقت الإغلاق $minutes دقائق بسبب عرض في الدقائق الأخيرة.',
      two: 'مُدّد وقت الإغلاق دقيقتين بسبب عرض في الدقائق الأخيرة.',
      one: 'مُدّد وقت الإغلاق دقيقة واحدة بسبب عرض في الدقائق الأخيرة.',
    );
    return '$_temp0';
  }

  @override
  String liveExtendedSeconds(int seconds) {
    String _temp0 = intl.Intl.pluralLogic(
      seconds,
      locale: localeName,
      other: 'مُدّد وقت الإغلاق $seconds ثانية بسبب عرض في الدقائق الأخيرة.',
      many: 'مُدّد وقت الإغلاق $seconds ثانية بسبب عرض في الدقائق الأخيرة.',
      few: 'مُدّد وقت الإغلاق $seconds ثوانٍ بسبب عرض في الدقائق الأخيرة.',
      two: 'مُدّد وقت الإغلاق ثانيتين بسبب عرض في الدقائق الأخيرة.',
      one: 'مُدّد وقت الإغلاق ثانية واحدة بسبب عرض في الدقائق الأخيرة.',
    );
    return '$_temp0';
  }

  @override
  String get liveExtendedGeneric =>
      'مُدّد وقت الإغلاق بسبب عرض في الدقائق الأخيرة.';

  @override
  String get liveExtendedManual => 'مدّد طارح المنافسة موعد الإغلاق.';

  @override
  String get liveHintServerTiming => 'يُعتمد وقت استلام العرض على خادم بافو.';

  @override
  String get liveHintSlowConnection => 'الاتصال بطيء، فقدّم عرضك مبكراً.';

  @override
  String get liveConnectionConnecting => 'جارٍ الاتصال…';

  @override
  String get liveConnectionLive => 'مباشر';

  @override
  String get liveConnectionReconnecting => 'جارٍ إعادة الاتصال…';

  @override
  String liveConnectionPolling(int seconds) {
    String _temp0 = intl.Intl.pluralLogic(
      seconds,
      locale: localeName,
      other: 'التحديثات المباشرة متأخرة، ونحدّث كل $seconds ثانية.',
      many: 'التحديثات المباشرة متأخرة، ونحدّث كل $seconds ثانية.',
      few: 'التحديثات المباشرة متأخرة، ونحدّث كل $seconds ثوانٍ.',
      two: 'التحديثات المباشرة متأخرة، ونحدّث كل ثانيتين.',
      one: 'التحديثات المباشرة متأخرة، ونحدّث كل ثانية.',
    );
    return '$_temp0';
  }

  @override
  String get liveConnectionOffline => 'لا يوجد اتصال بالإنترنت.';

  @override
  String get liveConnectionSubmitPaused =>
      'يتوقف تقديم العروض حتى يعود الاتصال.';

  @override
  String get liveInitialPhaseNote => 'قبل فترة التسعير النهائية يظهر عرضك فقط.';

  @override
  String get liveLadderTitle => 'ترتيب العروض';

  @override
  String liveLadderParticipant(int alias) {
    return 'المتنافس $alias';
  }

  @override
  String get liveLadderYou => 'أنتم';

  @override
  String liveLadderRowSemantics(int rank, String name, String amount) {
    return 'المرتبة $rank، $name، $amount';
  }

  @override
  String get liveMyOfferTitle => 'عرضك الحالي';

  @override
  String liveMyOfferReceivedAt(String time) {
    return 'استُلم في $time';
  }

  @override
  String liveMyOfferCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count عرض مقدّم',
      many: '$count عرضاً مقدّماً',
      few: '$count عروض مقدّمة',
      two: 'عرضان مقدّمان',
      one: 'عرض واحد مقدّم',
    );
    return '$_temp0';
  }

  @override
  String get liveMyOffersLink => 'سجل عروضي';

  @override
  String get liveComposerLabel => 'مبلغ عرضك';

  @override
  String get liveComposerSubmitFirst => 'تقديم العرض';

  @override
  String get liveComposerSubmitImprove => 'تحسين العرض';

  @override
  String get liveComposerSubmitRevise => 'تعديل العرض';

  @override
  String get liveComposerSubmitBafo => 'تقديم العرض النهائي';

  @override
  String liveComposerUseAmount(String amount) {
    return 'استخدم $amount';
  }

  @override
  String get liveComposerWholeRiyals => 'بالريال دون هللات';

  @override
  String get liveComposerDecimalsAllowed => 'يمكن إدخال الهللات (رقمان عشريان)';

  @override
  String liveComposerOpensIn(String time) {
    return 'تبدأ العروض خلال $time';
  }

  @override
  String liveComposerOpensAt(String time) {
    return 'تبدأ العروض في $time';
  }

  @override
  String get liveComposerClosed => 'أُغلق استقبال العروض.';

  @override
  String get liveComposerNotShortlisted =>
      'يجري طارح المنافسة جولة عرض نهائي مع قائمة مختصرة، ويبقى عرضكم الأخير قائماً.';

  @override
  String get liveSealedTitle => 'عروض بظرف مغلق';

  @override
  String get liveSealedBody => 'عرضكم مغلق، وتُفتح العروض عند الإغلاق.';

  @override
  String get liveSealedNoOffer =>
      'قدّموا عرضكم المغلق قبل الإغلاق، ويمكنكم تعديله حتى ذلك الحين.';

  @override
  String get liveNotLiveYet => 'لم تبدأ العروض بعد.';

  @override
  String offersHintRequiredNext(String direction, String amount) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'يجب ألا يزيد عرضك التالي عن $amount.',
      'auction': 'يجب ألا يقل عرضك التالي عن $amount.',
      'other': 'الحد التالي لعرضك: $amount.',
    });
    return '$_temp0';
  }

  @override
  String offersHintStartPrice(String direction, String amount) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'لا يتجاوز سعر السقف $amount.',
      'auction': 'لا يقل عن سعر الافتتاح $amount.',
      'other': 'سعر البداية: $amount.',
    });
    return '$_temp0';
  }

  @override
  String offersErrorStartPrice(String direction, String amount) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'لا يمكن أن يتجاوز عرضك سعر السقف $amount.',
      'auction': 'لا يمكن أن يقل عرضك عن سعر الافتتاح $amount.',
      'other': 'العرض لا يستوفي سعر البداية $amount.',
    });
    return '$_temp0';
  }

  @override
  String offersErrorStepNotMet(String direction, String amount) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'يجب ألا يزيد عرضك عن $amount.',
      'auction': 'يجب ألا يقل عرضك عن $amount.',
      'other': 'العرض لا يستوفي الحد الأدنى للتحسين ($amount).',
    });
    return '$_temp0';
  }

  @override
  String offersErrorGranularity(String amount) {
    return 'يجب أن يكون المبلغ من مضاعفات $amount.';
  }

  @override
  String offersErrorAmountTooLarge(String amount) {
    return 'يجب ألا يزيد المبلغ عن $amount.';
  }

  @override
  String offersErrorBafoReference(String direction, String amount) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'لا يمكن أن يزيد عرضك النهائي عن عرضك الأخير $amount.',
      'auction': 'لا يمكن أن يقل عرضك النهائي عن عرضك الأخير $amount.',
      'other': 'لا يمكن أن يكون عرضك النهائي أسوأ من عرضك الأخير $amount.',
    });
    return '$_temp0';
  }

  @override
  String get offersUseThisAmount => 'استخدم هذا المبلغ';

  @override
  String get offersConfirmTitle => 'تأكيد العرض';

  @override
  String get offersConfirmBafoTitle => 'تأكيد العرض النهائي';

  @override
  String get offersConfirmExclVat => 'لا يشمل ضريبة القيمة المضافة';

  @override
  String get offersConfirmSealed =>
      'عرضك مغلق ولا يراه غيرك، ويمكنك تعديله حتى الإغلاق.';

  @override
  String get offersConfirmBafo => 'هذا عرضك النهائي الوحيد ولا يمكن تعديله.';

  @override
  String get offersConfirmAction => 'تأكيد وإرسال';

  @override
  String get offersConfirmOutlierTitle => 'تحقق من المبلغ';

  @override
  String offersConfirmOutlierLower(String pct) {
    return 'هذا العرض أقل من عرضك الحالي بنسبة $pct.';
  }

  @override
  String offersConfirmOutlierHigher(String pct) {
    return 'هذا العرض أعلى من عرضك الحالي بنسبة $pct.';
  }

  @override
  String get offersConfirmOutlierSend => 'إرسال العرض بهذا المبلغ';

  @override
  String get offersConfirmOutlierEdit => 'تعديل المبلغ';

  @override
  String offersSubmitted(String time) {
    return 'استُلم عرضك في $time';
  }

  @override
  String get offersUnconfirmed => 'لم نتأكد من استلام عرضك. أعد المحاولة.';

  @override
  String get offersKeyReused =>
      'تعذّر تأكيد العرض. راجع المبلغ وأكّده من جديد.';

  @override
  String get offersRateLimited => 'انتظر لحظات قبل تقديم عرض جديد.';

  @override
  String get offersSealedReceived => 'استُلم عرضكم المغلق';

  @override
  String get offersReceiptSeq => 'رقم الاستلام';

  @override
  String get offersReceiptTime => 'وقت الاستلام';

  @override
  String get offersReceiptAmount => 'المبلغ';

  @override
  String get offersStageSealed => 'مغلق';

  @override
  String get offersStageInitial => 'أولي';

  @override
  String get offersStageLive => 'مباشر';

  @override
  String get offersStageBafo => 'نهائي';

  @override
  String get offersVoided => 'ملغى';

  @override
  String get offersMyEmptyTitle => 'لم تقدّموا عروضاً بعد';

  @override
  String get offersMyEmptyMessage => 'قدّموا عرضكم من غرفة العروض.';

  @override
  String offersMySeq(int seq) {
    return 'العرض رقم $seq';
  }

  @override
  String offersMyChangeDown(String pct) {
    return 'أقل من عرضكم السابق بنسبة $pct';
  }

  @override
  String offersMyChangeUp(String pct) {
    return 'أعلى من عرضكم السابق بنسبة $pct';
  }

  @override
  String bafoInvite(String cutoff) {
    return 'أنتم مدعوون لتقديم أفضل وآخر عرض قبل $cutoff.';
  }

  @override
  String bafoReferenceAmount(String amount) {
    return 'عرضكم الأخير: $amount';
  }

  @override
  String bafoRule(String direction) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'لا يمكن أن يكون عرضك النهائي أعلى من عرضك الأخير.',
      'auction': 'لا يمكن أن يكون عرضك النهائي أقل من عرضك الأخير.',
      'other': 'لا يمكن أن يكون عرضك النهائي أسوأ من عرضك الأخير.',
    });
    return '$_temp0';
  }

  @override
  String get bafoSubmitted => 'استُلم عرضكم النهائي.';

  @override
  String get qaTitle => 'الاستفسارات';

  @override
  String get qaAskAction => 'اطرح سؤالاً';

  @override
  String get qaAnnounceAction => 'انشر إعلاناً لجميع المتنافسين';

  @override
  String get qaReplyAction => 'ردّ';

  @override
  String get qaReplyTitle => 'الرد على الاستفسار';

  @override
  String get qaQuestionLabel => 'نص السؤال';

  @override
  String get qaAnnouncementLabel => 'نص الإعلان';

  @override
  String get qaReplyLabel => 'نص الرد';

  @override
  String get qaQuestionHelper => 'يرى جميع المتنافسين السؤال دون اسم منشأتكم.';

  @override
  String get qaSend => 'إرسال';

  @override
  String get qaPosted => 'نُشر الاستفسار.';

  @override
  String get qaAnnouncementPosted => 'نُشر الإعلان.';

  @override
  String get qaReplyPosted => 'نُشر الرد.';

  @override
  String get qaClosedTitle => 'أُغلقت الاستفسارات';

  @override
  String get qaClosedBody =>
      'لا يمكن نشر استفسارات جديدة في حالة المنافسة الحالية.';

  @override
  String get qaEmptyTitle => 'لا توجد استفسارات بعد';

  @override
  String get qaEmptyMessage =>
      'تظهر هنا الأسئلة والردود وإعلانات طارح المنافسة.';

  @override
  String qaAuthorParticipant(int alias) {
    return 'المتنافس $alias';
  }

  @override
  String qaAuthorParticipantNamed(int alias, String organization) {
    return 'المتنافس $alias · $organization';
  }

  @override
  String get qaAuthorMe => 'أنتم';

  @override
  String get qaAuthorUnknown => 'متنافس';

  @override
  String get qaAnnouncement => 'إعلان';

  @override
  String qaNewMessages(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count رسالة جديدة',
      many: '$count رسالة جديدة',
      few: '$count رسائل جديدة',
      two: 'رسالتان جديدتان',
      one: 'رسالة جديدة',
    );
    return '$_temp0';
  }

  @override
  String qaRepliesCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count رد',
      many: '$count رداً',
      few: '$count ردود',
      two: 'ردّان',
      one: 'رد واحد',
    );
    return '$_temp0';
  }

  @override
  String get issuerWebOnly => 'متاح في لوحة التحكم على الويب.';

  @override
  String get issuerWebOnlyActions =>
      'تمديد الإغلاق وجولة العرض النهائي والترسية وإلغاؤها والإغلاق دون ترسية متاحة في لوحة التحكم على الويب.';

  @override
  String get issuerPlanRequired =>
      'تحتاج منشأتكم إلى باقة فعّالة لطرح المنافسات.';

  @override
  String get issuerNoValue => '—';

  @override
  String get issuerYes => 'نعم';

  @override
  String get issuerNo => 'لا';

  @override
  String get issuerDiscard => 'تجاهل';

  @override
  String issuerParticipantAlias(int alias) {
    return 'المتنافس $alias';
  }

  @override
  String issuerParticipantLabel(int alias, String name) {
    return 'المتنافس $alias · $name';
  }

  @override
  String get issuerListCreate => 'منافسة جديدة';

  @override
  String get issuerListSearchHint => 'ابحث بعنوان المنافسة';

  @override
  String get issuerListSegmentActive => 'النشطة';

  @override
  String get issuerListSegmentDrafts => 'المسودات';

  @override
  String get issuerListSegmentEnded => 'المنتهية';

  @override
  String get issuerListEmptyActiveTitle => 'لا توجد منافسات نشطة';

  @override
  String get issuerListEmptyActiveMessage =>
      'تظهر هنا المنافسات المجدولة والمفتوحة للعروض وقيد التقييم.';

  @override
  String get issuerListEmptyDraftsTitle => 'لا توجد مسودات';

  @override
  String get issuerListEmptyDraftsMessage =>
      'تُحفظ المنافسة الجديدة مسودةً حتى تنشرها.';

  @override
  String get issuerListEmptyEndedTitle => 'لا توجد منافسات منتهية';

  @override
  String get issuerListEmptyEndedMessage =>
      'تظهر هنا المنافسات التي تمت ترسيتها أو أُغلقت دون ترسية أو أُلغيت.';

  @override
  String get issuerListNoResultsTitle => 'لا توجد نتائج مطابقة';

  @override
  String get issuerListNoResultsMessage => 'جرّب كلمات بحث أخرى.';

  @override
  String issuerListOpensAt(String time) {
    return 'تبدأ العروض في $time';
  }

  @override
  String issuerListUpdatedAt(String date) {
    return 'آخر تحديث في $date';
  }

  @override
  String issuerListCounts(int invited, int joined, int withOffers) {
    return 'المدعوون $invited · المنضمون $joined · بعروض $withOffers';
  }

  @override
  String get issuerCreateTitle => 'منافسة جديدة';

  @override
  String get issuerCreateStepType => 'النوع والإعداد المسبق';

  @override
  String get issuerCreateStepBasics => 'البيانات الأساسية';

  @override
  String get issuerCreateStepSchedule => 'الأسعار والمواعيد';

  @override
  String get issuerCreateStepReview => 'المراجعة والحفظ';

  @override
  String get issuerCreateDirectionTitle => 'نوع المنافسة';

  @override
  String issuerCreateIssuerRole(String direction) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'أنت المشتري',
      'auction': 'أنت البائع',
      'other': 'طارح المنافسة',
    });
    return '$_temp0';
  }

  @override
  String get issuerCreateAuctionDisabled =>
      'المزايدات غير مفعّلة لمنشأتك. تواصل مع بافو.';

  @override
  String get issuerCreateFormatTitle => 'طريقة تقديم العروض';

  @override
  String get issuerCreateFormatLiveHint =>
      'عروض مباشرة قابلة للتحسين حتى الإغلاق';

  @override
  String get issuerCreateFormatSealedHint =>
      'عرض مغلق لكل متنافس يُفتح عند الإغلاق';

  @override
  String get issuerCreatePresetTitle => 'الإعداد المسبق للقواعد';

  @override
  String get issuerCreatePresetRequired => 'اختر إعداداً مسبقاً للمتابعة.';

  @override
  String get issuerCreateNoPreset =>
      'لا يوجد إعداد مسبق لهذا الاختيار في التطبيق. يمكنك إنشاء هذه المنافسة من لوحة التحكم على الويب.';

  @override
  String get issuerCreateAdvancedOnWeb =>
      'الإعدادات المتقدمة متاحة في لوحة التحكم على الويب.';

  @override
  String get issuerCreatePricesTitle => 'الأسعار';

  @override
  String get issuerCreateScheduleTitle => 'المواعيد';

  @override
  String get issuerCreateDiscardTitle => 'تجاهل المنافسة؟';

  @override
  String get issuerCreateDiscardMessage => 'لن تُحفظ البيانات التي أدخلتها.';

  @override
  String get issuerCreateSaved =>
      'حُفظت المسودة. ادعُ المتنافسين وأضف المستندات ثم انشرها.';

  @override
  String issuerPresetChipStep(String step) {
    return 'خطوة التحسين $step';
  }

  @override
  String get issuerPresetChipRankFull => 'الترتيب ظاهر';

  @override
  String get issuerPresetChipLeadingFlag => 'مؤشر العرض المتصدر';

  @override
  String get issuerPresetChipRankHidden => 'الترتيب مخفي';

  @override
  String get issuerPresetChipPricesShown => 'الأسعار ظاهرة';

  @override
  String get issuerPresetChipAutoExtend => 'تمديد تلقائي';

  @override
  String get issuerPresetChipBafo => 'جولة عرض نهائي';

  @override
  String get issuerFieldTitle => 'عنوان المنافسة';

  @override
  String get issuerFieldDescription => 'الوصف ونطاق العمل';

  @override
  String get issuerFieldDescriptionHelper => 'مطلوب قبل النشر.';

  @override
  String get issuerFieldCategoryOther => 'حدّد الفئة';

  @override
  String get issuerFieldCategoryNoAuction => 'لا تسمح هذه الفئة بالمزايدات.';

  @override
  String issuerFieldStartPrice(String direction) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'سعر السقف',
      'auction': 'سعر الافتتاح',
      'other': 'سعر البداية',
    });
    return '$_temp0';
  }

  @override
  String issuerFieldStartPriceHelper(String direction) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'اختياري. لا يُقبل عرض يتجاوزه.',
      'auction': 'مطلوب قبل النشر. لا يُقبل عرض أقل منه.',
      'other': 'اختياري.',
    });
    return '$_temp0';
  }

  @override
  String issuerFieldReservePrice(String direction) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'السعر المستهدف',
      'auction': 'الحد الأدنى المقبول',
      'other': 'السعر المستهدف أو الحد الأدنى المقبول',
    });
    return '$_temp0';
  }

  @override
  String get issuerFieldReserveHelper =>
      'اختياري. مخفي عن المتنافسين ويؤثر في الترسية فقط.';

  @override
  String issuerFieldReserveVersusStart(String direction) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'يجب ألا يزيد السعر المستهدف عن سعر السقف.',
      'auction': 'يجب ألا يقل الحد الأدنى المقبول عن سعر الافتتاح.',
      'other':
          'السعر المستهدف أو الحد الأدنى المقبول لا يتوافق مع سعر البداية.',
    });
    return '$_temp0';
  }

  @override
  String get issuerFieldOpensOnPublish => 'فور النشر';

  @override
  String get issuerFieldOpensAtTime => 'في موعد محدد';

  @override
  String get issuerFieldRiyadhTime => 'المواعيد بتوقيت الرياض.';

  @override
  String get issuerFieldCloseBeforeOpen =>
      'يجب أن يكون موعد الإغلاق بعد بدء استقبال العروض.';

  @override
  String get issuerFieldOpensInPast => 'اختر موعداً لم يمضِ بعد.';

  @override
  String issuerFieldDurationTooShort(int minutes) {
    String _temp0 = intl.Intl.pluralLogic(
      minutes,
      locale: localeName,
      other: '$minutes دقيقة',
      many: '$minutes دقيقة',
      few: '$minutes دقائق',
      two: 'دقيقتين',
      one: 'دقيقة واحدة',
    );
    return 'يجب ألا تقل مدة استقبال العروض عن $_temp0.';
  }

  @override
  String issuerFieldDurationTooLong(int days) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: '$days يوم',
      many: '$days يوماً',
      few: '$days أيام',
      two: 'يومين',
      one: 'يوم واحد',
    );
    return 'يجب ألا تزيد المدة عن $_temp0.';
  }

  @override
  String get issuerSchedulePreviewTitle => 'الجدول الزمني المتوقع';

  @override
  String get issuerSchedulePreviewNote => 'تقديري، يُثبَّت عند النشر';

  @override
  String get issuerReviewEdit => 'تعديل';

  @override
  String get issuerReviewSave => 'حفظ المسودة';

  @override
  String get issuerReviewDraftNote =>
      'تُحفظ المنافسة مسودةً. يمكنك بعدها إضافة المستندات ودعوة المتنافسين ثم نشرها.';

  @override
  String get issuerReviewNoDescription => 'لم يُضف بعد (مطلوب قبل النشر)';

  @override
  String get issuerReviewDescriptionAdded => 'أُضيف';

  @override
  String get issuerActionsMenu => 'الإجراءات';

  @override
  String get issuerActionPublish => 'نشر المنافسة';

  @override
  String get issuerActionEdit => 'تعديل البيانات';

  @override
  String get issuerActionInvite => 'دعوة متنافسين';

  @override
  String get issuerActionInviteMore => 'دعوة المزيد';

  @override
  String get issuerActionDocuments => 'المستندات';

  @override
  String get issuerActionExtend => 'تمديد الإغلاق';

  @override
  String get issuerActionStartBafo => 'بدء جولة العرض النهائي';

  @override
  String get issuerActionAward => 'الترسية';

  @override
  String get issuerActionRevokeAward => 'إلغاء الترسية';

  @override
  String get issuerActionCloseWithoutAward => 'إغلاق دون ترسية';

  @override
  String get issuerActionCancel => 'إلغاء المنافسة';

  @override
  String get issuerActionDeleteDraft => 'حذف المسودة';

  @override
  String get issuerDetailCreatedViaApi => 'أُنشئت عبر واجهة برمجة التطبيقات';

  @override
  String get issuerDetailLeadingOffer => 'العرض المتصدر';

  @override
  String get issuerDetailCounts => 'ملخص المشاركة';

  @override
  String get issuerDetailCreatedBy => 'أنشأها';

  @override
  String get issuerCountInvitations => 'المدعوون';

  @override
  String get issuerCountJoined => 'المنضمون';

  @override
  String get issuerCountDeclined => 'المعتذرون';

  @override
  String get issuerCountWithOffers => 'قدّموا عروضاً';

  @override
  String get issuerCountOffers => 'العروض';

  @override
  String get issuerCountComments => 'الاستفسارات';

  @override
  String get issuerNavLive => 'المتابعة المباشرة';

  @override
  String get issuerNavOffers => 'سجل العروض';

  @override
  String get issuerNavParticipants => 'المتنافسون والدعوات';

  @override
  String get issuerNavQa => 'الاستفسارات';

  @override
  String get issuerNavAward => 'التقييم والترسية';

  @override
  String get issuerNavDocuments => 'المستندات';

  @override
  String get issuerDocumentsManage => 'إدارة';

  @override
  String issuerAwardSummary(String participant) {
    return 'تمت الترسية على $participant';
  }

  @override
  String get issuerTimelinePublished => 'النشر';

  @override
  String get issuerTimelineScheduledClose => 'موعد الإغلاق المجدول';

  @override
  String get issuerTimelineEffectiveClose => 'موعد الإغلاق الحالي';

  @override
  String get issuerTimelineHardStop => 'أقصى موعد للإغلاق';

  @override
  String get issuerTimelineExtensions => 'مرات التمديد';

  @override
  String get issuerTimelineClosed => 'الإغلاق';

  @override
  String get issuerTimelineAwarded => 'الترسية';

  @override
  String get issuerTimelineNotAwarded => 'الإغلاق دون ترسية';

  @override
  String get issuerTimelineCancelled => 'الإلغاء';

  @override
  String get issuerChecklistTitle => 'قبل النشر';

  @override
  String get issuerChecklistDescription => 'أضف الوصف ونطاق العمل';

  @override
  String get issuerChecklistSchedule => 'حدّد موعد الإغلاق';

  @override
  String get issuerChecklistStartPrice => 'حدّد سعر الافتتاح';

  @override
  String get issuerChecklistOtherText => 'حدّد الفئة';

  @override
  String issuerChecklistInvitations(int min, int current) {
    String _temp0 = intl.Intl.pluralLogic(
      min,
      locale: localeName,
      other: '$min متنافس',
      many: '$min متنافساً',
      few: '$min متنافسين',
      two: 'متنافسَين',
      one: 'متنافساً واحداً',
    );
    return 'ادعُ $_temp0 على الأقل (المدعوون الآن: $current)';
  }

  @override
  String get issuerPublishTitle => 'نشر المنافسة';

  @override
  String get issuerPublishNote =>
      'عند النشر تُرسل الدعوات إلى المدعوين، وتصبح القواعد ثابتة.';

  @override
  String get issuerPublishConfirm => 'نشر';

  @override
  String get issuerPublishDone => 'نُشرت المنافسة وأُرسلت الدعوات';

  @override
  String get issuerPublishFixFields => 'أكمل ما يلي ثم أعد المحاولة:';

  @override
  String issuerPublishMissingInvitations(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count مدعو',
      many: '$count مدعواً',
      few: '$count مدعوين',
      two: 'مدعوَّين',
      one: 'مدعواً واحداً',
    );
    return 'أضف $_temp0 على الأقل.';
  }

  @override
  String get issuerPublishCapacity =>
      'بلغ عدد الفعاليات المباشرة المتزامنة حدّه في هذه الفترة. اختر موعداً آخر.';

  @override
  String get issuerCancelTitle => 'إلغاء المنافسة';

  @override
  String get issuerCancelDone => 'أُلغيت المنافسة.';

  @override
  String get issuerDeleteTitle => 'حذف المسودة؟';

  @override
  String get issuerDeleteMessage =>
      'تُحذف المسودة ودعواتها، ولا يمكن استعادتها.';

  @override
  String get issuerDeleteDone => 'حُذفت المسودة.';

  @override
  String get issuerSponsorshipTitle => 'رسوم المشاركة';

  @override
  String issuerSponsorshipMode(String mode) {
    String _temp0 = intl.Intl.selectLogic(mode, {
      'all': 'تغطية الرسوم لجميع المدعوين',
      'selected': 'تغطية الرسوم لمدعوين محددين',
      'other': 'لا تغطية للرسوم',
    });
    return '$_temp0';
  }

  @override
  String get issuerSponsorshipCap => 'الحد الأقصى للمتنافسين المغطّين';

  @override
  String get issuerSponsorshipFunded => 'التصاريح الممولة';

  @override
  String get issuerSponsorshipJoined => 'تصاريح مستخدمة';

  @override
  String get issuerSponsorshipReserved => 'تصاريح محجوزة';

  @override
  String get issuerSponsorshipFreeSlots => 'تصاريح متاحة';

  @override
  String get issuerSponsorshipPending => 'بانتظار الدفع';

  @override
  String get issuerSponsorshipUnused => 'تصاريح غير مستخدمة';

  @override
  String get issuerSponsorshipOnWeb =>
      'تُدار رسوم المشاركة ومدفوعاتها من لوحة تحكم بافو على الويب.';

  @override
  String get issuerEditTitle => 'تعديل المنافسة';

  @override
  String get issuerEditSaved => 'حُفظت التعديلات.';

  @override
  String get issuerEditNotifyNote => 'سيُبلَّغ المتنافسون بالتحديث.';

  @override
  String get issuerEditRulesFixed => 'القواعد والأسعار ثابتة بعد النشر.';

  @override
  String get issuerEditLiveScope =>
      'أثناء استقبال العروض يمكن تعديل العنوان والوصف فقط.';

  @override
  String get issuerEditTypeOnWeb =>
      'يُغيَّر نوع المنافسة وطريقة العروض والقواعد من لوحة التحكم على الويب.';

  @override
  String get issuerEditDiscardTitle => 'تجاهل التعديلات؟';

  @override
  String get issuerEditDiscardMessage => 'لن تُحفظ التعديلات التي أجريتها.';

  @override
  String get issuerInviteTitle => 'دعوة متنافسين';

  @override
  String get issuerInviteTabSuggestions => 'مقترحون';

  @override
  String get issuerInviteTabEmail => 'بالبريد';

  @override
  String get issuerInviteTabVendors => 'دليل الموردين';

  @override
  String get issuerInviteSearchSuggestions => 'ابحث باسم المنشأة';

  @override
  String get issuerInviteSearchVendors => 'ابحث في دليل الموردين';

  @override
  String get issuerInviteNoSuggestions => 'لا توجد منشآت مقترحة مطابقة.';

  @override
  String get issuerInviteNoVendors => 'لا يوجد موردون مطابقون.';

  @override
  String get issuerInviteVerified => 'منشأة موثّقة';

  @override
  String get issuerInviteMatchCategory => 'نفس الفئة';

  @override
  String get issuerInviteMatchRegion => 'نفس المنطقة';

  @override
  String get issuerInviteHasPlan => 'لديها باقة فعّالة';

  @override
  String get issuerInviteVendorRegistered => 'مسجّل في بافو';

  @override
  String get issuerInviteEmailsLabel => 'عناوين البريد الإلكتروني';

  @override
  String get issuerInviteEmailsHelper =>
      'افصل بين العناوين بفاصلة أو مسافة أو سطر جديد.';

  @override
  String get issuerInviteEmailsAdd => 'إضافة';

  @override
  String issuerInviteEmailsAdded(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'أُضيف $count عنوان.',
      many: 'أُضيف $count عنواناً.',
      few: 'أُضيفت $count عناوين.',
      two: 'أُضيف عنوانان.',
      one: 'أُضيف عنوان واحد.',
    );
    return '$_temp0';
  }

  @override
  String issuerInviteEmailsInvalid(String emails) {
    return 'عناوين غير صالحة: $emails';
  }

  @override
  String issuerInviteStaged(int count) {
    return 'المختارون ($count)';
  }

  @override
  String get issuerInviteStagedEmpty =>
      'اختر من المقترحين أو دليل الموردين، أو أضف عناوين بريد.';

  @override
  String issuerInviteRemove(String name) {
    return 'إزالة $name';
  }

  @override
  String get issuerInviteCoverFees => 'تغطية رسوم المشاركة';

  @override
  String issuerInviteFreeSlots(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count تصريح مغطّى متاح.',
      many: '$count تصريحاً مغطّى متاحاً.',
      few: '$count تصاريح مغطّاة متاحة.',
      two: 'تصريحان مغطّيان متاحان.',
      one: 'تصريح مغطّى واحد متاح.',
      zero: 'لا توجد تصاريح مغطّاة متاحة.',
    );
    return '$_temp0';
  }

  @override
  String get issuerInviteNothingSent =>
      'لم تُرسل أي دعوة. صحّح الصفوف المحددة ثم أعد المحاولة.';

  @override
  String get issuerInviteFeesOnWeb =>
      'تُدفع رسوم مشاركة هذه الدعوات من لوحة تحكم بافو على الويب. لم تُرسل الدعوات.';

  @override
  String get issuerInviteSendWithoutFees => 'إرسال دون تغطية الرسوم';

  @override
  String get issuerInviteChooseFirst => 'اختر المدعوين أولاً';

  @override
  String issuerInviteSend(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'إرسال $count دعوة',
      many: 'إرسال $count دعوة',
      few: 'إرسال $count دعوات',
      two: 'إرسال دعوتين',
      one: 'إرسال دعوة واحدة',
    );
    return '$_temp0';
  }

  @override
  String issuerInviteAdd(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'إضافة $count مدعو',
      many: 'إضافة $count مدعواً',
      few: 'إضافة $count مدعوين',
      two: 'إضافة مدعوَّين',
      one: 'إضافة مدعو واحد',
    );
    return '$_temp0';
  }

  @override
  String get issuerInviteDone => 'أُرسلت الدعوات.';

  @override
  String get issuerInviteAddedToDraft =>
      'أُضيف المدعوون إلى المسودة، وتُرسل الدعوات عند النشر.';

  @override
  String get issuerInviteRowDuplicate => 'مدعو من قبل في هذه المنافسة.';

  @override
  String get issuerInviteRowOwnOrganization => 'لا يمكن دعوة منشأتكم.';

  @override
  String get issuerInviteRowVendorBlocked => 'هذا المورد محظور في دليلكم.';

  @override
  String get issuerInviteRowVendorNotFound => 'لم يُعثر على هذا المورد.';

  @override
  String get issuerInviteDiscardTitle => 'تجاهل المختارين؟';

  @override
  String get issuerInviteDiscardMessage =>
      'لم تُرسل الدعوات بعد، ولن يُحفظ اختيارك.';

  @override
  String get issuerParticipantsTitle => 'المتنافسون والدعوات';

  @override
  String issuerParticipantsFilterAll(int count) {
    return 'الكل ($count)';
  }

  @override
  String issuerParticipantsFilterStatus(String status, int count) {
    return '$status ($count)';
  }

  @override
  String get issuerParticipantsFilterEmpty => 'لا توجد دعوات بهذه الحالة.';

  @override
  String get issuerParticipantsEmptyTitle => 'لم تُرسل دعوات بعد';

  @override
  String get issuerParticipantsEmptyMessage =>
      'ادعُ المتنافسين من المقترحين أو بالبريد الإلكتروني أو من دليل الموردين.';

  @override
  String issuerInvitationStatus(String status) {
    String _temp0 = intl.Intl.selectLogic(status, {
      'draft': 'مسودة',
      'sent': 'أُرسلت',
      'viewed': 'اطُّلع عليها',
      'joined': 'انضم',
      'declined': 'اعتذر',
      'revoked': 'أُلغيت',
      'expired': 'انتهت',
      'other': 'غير معروفة',
    });
    return '$_temp0';
  }

  @override
  String issuerCoverage(String coverage) {
    String _temp0 = intl.Intl.selectLogic(coverage, {
      'sponsored': 'مغطّاة منكم',
      'own_plan': 'باقة المتنافس',
      'none': 'غير مغطّاة',
      'other': 'غير معروفة',
    });
    return '$_temp0';
  }

  @override
  String issuerPassStatus(String status) {
    String _temp0 = intl.Intl.selectLogic(status, {
      'pending': 'تصريح بانتظار الدفع',
      'reserved': 'تصريح محجوز',
      'joined': 'تصريح مستخدَم',
      'released': 'تصريح مُعاد',
      'unused': 'تصريح غير مستخدَم',
      'void': 'تصريح ملغى',
      'other': 'تصريح',
    });
    return '$_temp0';
  }

  @override
  String issuerInvitationSentAt(String time) {
    return 'أُرسلت $time';
  }

  @override
  String issuerInvitationViewedAt(String time) {
    return 'اطُّلع عليها $time';
  }

  @override
  String issuerInvitationJoinedAt(String time) {
    return 'انضم $time';
  }

  @override
  String issuerInvitationDeclinedAt(String time) {
    return 'اعتذر $time';
  }

  @override
  String issuerInvitationRevokedAt(String time) {
    return 'أُلغيت $time';
  }

  @override
  String issuerInvitationReason(String reason) {
    return 'السبب: $reason';
  }

  @override
  String get issuerInvitationResend => 'إعادة الإرسال';

  @override
  String get issuerInvitationRevoke => 'إلغاء الدعوة';

  @override
  String get issuerInvitationRemove => 'إزالة المدعو';

  @override
  String get issuerInvitationResent => 'أُعيد إرسال الدعوة.';

  @override
  String get issuerInvitationRevoked => 'أُلغيت الدعوة.';

  @override
  String get issuerInvitationRemoved => 'أُزيل المدعو من المسودة.';

  @override
  String get issuerInvitationResendLimit =>
      'بلغت الحد اليومي لإعادة إرسال هذه الدعوة.';

  @override
  String get issuerRevokeTitle => 'إلغاء الدعوة؟';

  @override
  String get issuerRevokeMessage => 'لن يتمكن المدعو من الانضمام بهذه الدعوة.';

  @override
  String get issuerRemoveInviteeTitle => 'إزالة المدعو؟';

  @override
  String get issuerRemoveInviteeMessage => 'يُحذف هذا المدعو من المسودة.';

  @override
  String get issuerDocumentsTitle => 'مستندات المنافسة';

  @override
  String get issuerDocumentsUpload => 'رفع مستند';

  @override
  String get issuerDocumentsLimits =>
      'PDF أو Word أو Excel أو صور أو ZIP، بحد أقصى 100 ميجابايت للملف.';

  @override
  String get issuerDocumentsAddendumNote =>
      'المستندات المضافة بعد النشر تُعلَن للمتنافسين ملحقاً.';

  @override
  String get issuerDocumentsDeleteClosed =>
      'لا يمكن حذف المستندات بعد بدء استقبال العروض.';

  @override
  String get issuerDocumentsWebOnly =>
      'الروابط الخارجية ومستندات الدعوة تُضاف من لوحة التحكم على الويب.';

  @override
  String get issuerDocumentsEmptyTitle => 'لا توجد مستندات';

  @override
  String get issuerDocumentsEmptyMessage =>
      'ارفع كراسة الشروط والمواصفات ليطّلع عليها المتنافسون بعد انضمامهم.';

  @override
  String get issuerDocumentsUploadFailed => 'تعذّر رفع الملف.';

  @override
  String issuerDocumentsUploading(String name) {
    return 'جارٍ رفع $name';
  }

  @override
  String get issuerDocumentDeleteTitle => 'حذف المستند؟';

  @override
  String issuerDocumentDeleteMessage(String name) {
    return 'يُحذف المستند $name من المنافسة.';
  }

  @override
  String get issuerDocumentDelete => 'حذف المستند';

  @override
  String get issuerLiveTitle => 'المتابعة المباشرة';

  @override
  String get issuerLiveConnected => 'مباشر';

  @override
  String get issuerLiveReconnecting => 'جارٍ إعادة الاتصال…';

  @override
  String issuerLivePolling(int seconds) {
    return 'التحديثات المباشرة متأخرة، ونحدّث كل $seconds ثوانٍ.';
  }

  @override
  String get issuerLiveDraftTitle => 'لم تُنشر المنافسة بعد';

  @override
  String get issuerLiveDraftMessage =>
      'تبدأ المتابعة المباشرة بعد نشر المنافسة.';

  @override
  String issuerLiveOnline(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count متنافس متصل الآن',
      many: '$count متنافساً متصلاً الآن',
      few: '$count متنافسين متصلون الآن',
      two: 'متنافسان متصلان الآن',
      one: 'متنافس واحد متصل الآن',
      zero: 'لا يوجد متنافسون متصلون الآن',
    );
    return '$_temp0';
  }

  @override
  String issuerLiveExtended(String reason) {
    String _temp0 = intl.Intl.selectLogic(reason, {
      'auto': 'مُدّد الإغلاق تلقائياً بسبب عرض في الدقائق الأخيرة.',
      'manual': 'مدّد طارح المنافسة موعد الإغلاق.',
      'admin': 'مدّدت بافو موعد الإغلاق.',
      'other': 'مُدّد موعد الإغلاق.',
    });
    return '$_temp0';
  }

  @override
  String issuerLiveBafoProgress(int submitted, int shortlist) {
    return 'قدّم $submitted من $shortlist عروضهم النهائية';
  }

  @override
  String get issuerLiveNoOffers => 'لم تصل عروض بعد';

  @override
  String get issuerLiveSealedLock => 'تُفتح العروض عند الإغلاق';

  @override
  String issuerLiveReceivedAt(String time) {
    return 'استُلم في $time';
  }

  @override
  String issuerLiveReserveMet(String direction) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'تحقق السعر المستهدف',
      'auction': 'تحقق الحد الأدنى المقبول',
      'other': 'تحقق السعر المستهدف أو الحد الأدنى المقبول',
    });
    return '$_temp0';
  }

  @override
  String issuerLiveReserveNotMet(String direction) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'لم يتحقق السعر المستهدف',
      'auction': 'لم يتحقق الحد الأدنى المقبول',
      'other': 'لم يتحقق السعر المستهدف أو الحد الأدنى المقبول',
    });
    return '$_temp0';
  }

  @override
  String issuerLiveImprovement(String direction) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'التوفير مقارنة بسعر السقف',
      'auction': 'الزيادة مقارنة بسعر الافتتاح',
      'other': 'التحسّن مقارنة بسعر البداية',
    });
    return '$_temp0';
  }

  @override
  String issuerLiveLeaderAnnouncement(String participant, String amount) {
    return 'العرض المتصدر الآن من $participant: $amount';
  }

  @override
  String get issuerLiveRankingTitle => 'ترتيب المتنافسين';

  @override
  String get issuerLiveNoParticipants => 'لم ينضم متنافسون بعد.';

  @override
  String issuerLiveRank(int rank) {
    return 'المرتبة $rank';
  }

  @override
  String get issuerLiveSubmitted => 'قدّم عرضاً';

  @override
  String get issuerLiveNotSubmitted => 'لم يقدّم عرضاً بعد';

  @override
  String issuerLiveFirstOffer(String amount) {
    return 'العرض الأول: $amount';
  }

  @override
  String issuerLiveOffersCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count عرض',
      many: '$count عرضاً',
      few: '$count عروض',
      two: 'عرضان',
      one: 'عرض واحد',
      zero: 'لا عروض',
    );
    return '$_temp0';
  }

  @override
  String issuerLiveLastOffer(String time) {
    return 'آخر عرض $time';
  }

  @override
  String get issuerLiveBafoShortlisted => 'في القائمة المختصرة';

  @override
  String get issuerLiveBafoSubmitted => 'قدّم العرض النهائي';

  @override
  String get issuerLiveExtendOnWeb =>
      'تمديد الإغلاق متاح في لوحة التحكم على الويب.';

  @override
  String get issuerOffersTitle => 'سجل العروض';

  @override
  String get issuerOffersEmptyTitle => 'لم تصل عروض بعد';

  @override
  String get issuerOffersEmptyMessage => 'تظهر العروض هنا فور استلامها.';

  @override
  String issuerOfferSeq(int seq) {
    return 'العرض رقم $seq';
  }

  @override
  String get issuerOfferSealed => 'مغلق';

  @override
  String issuerOfferStage(String stage) {
    String _temp0 = intl.Intl.selectLogic(stage, {
      'sealed': 'مغلق',
      'initial': 'أولي',
      'live': 'مباشر',
      'bafo': 'نهائي',
      'other': '—',
    });
    return '$_temp0';
  }

  @override
  String issuerOfferChannel(String channel) {
    String _temp0 = intl.Intl.selectLogic(channel, {
      'web': 'الويب',
      'ios': 'iOS',
      'android': 'أندرويد',
      'api': 'واجهة برمجية',
      'other': 'غير محدد',
    });
    return '$_temp0';
  }

  @override
  String get issuerOfferVoided => 'ملغى';

  @override
  String get issuerAwardTitle => 'التقييم والترسية';

  @override
  String get issuerAwardOnWeb => 'تتم الترسية من لوحة التحكم على الويب.';

  @override
  String get issuerAwardChangesOnWeb =>
      'إلغاء الترسية متاح في لوحة التحكم على الويب.';

  @override
  String get issuerAwardWinner => 'الترسية';

  @override
  String get issuerAwardRevokedTitle => 'ترسية ملغاة';

  @override
  String issuerAwardRevoked(String date, String reason) {
    return 'أُلغيت هذه الترسية في $date. السبب: $reason';
  }

  @override
  String get issuerAwardCr => 'السجل التجاري';

  @override
  String get issuerAwardVat => 'الرقم الضريبي';

  @override
  String get issuerAwardLeading => 'العرض المتصدر عند الترسية';

  @override
  String get issuerAwardRank => 'الترتيب عند الترسية';

  @override
  String get issuerAwardJustification => 'مبرر الترسية';

  @override
  String get issuerAwardMessage => 'رسالة إلى المتنافس الفائز';

  @override
  String get issuerAwardNotes => 'ملاحظات داخلية';

  @override
  String get issuerAwardBy => 'تمت الترسية بواسطة';

  @override
  String get issuerAwardAt => 'تاريخ الترسية';

  @override
  String get issuerAwardOfferAt => 'وقت استلام العرض';

  @override
  String get issuerAwardErpSync => 'المزامنة مع نظام تخطيط الموارد';

  @override
  String issuerAwardErpStatus(String status) {
    String _temp0 = intl.Intl.selectLogic(status, {
      'pending': 'قيد الانتظار',
      'synced': 'تمت المزامنة',
      'failed': 'تعذّرت المزامنة',
      'not_required': 'غير مطلوبة',
      'other': '—',
    });
    return '$_temp0';
  }

  @override
  String get issuerAwardLedgerHash => 'بصمة السجل';

  @override
  String get issuerAwardStandings => 'الترتيب النهائي';

  @override
  String issuerAwardChange(String value) {
    return 'التحسّن $value';
  }

  @override
  String get issuerReportTitle => 'تقرير النتائج';

  @override
  String get issuerReportShareHint =>
      'يفتح التقرير في عارض الملفات، ومنه يمكنك مشاركته.';

  @override
  String get issuerReportArabic => 'بالعربية';

  @override
  String get issuerReportEnglish => 'بالإنجليزية';

  @override
  String get issuerReportGenerating => 'جارٍ إعداد التقرير…';

  @override
  String get issuerReportFailed => 'تعذّر تجهيز التقرير. حاول مرة أخرى.';

  @override
  String get issuerReportTimeout =>
      'ما زال التقرير قيد الإعداد. حاول بعد قليل.';

  @override
  String invitationsJoinBy(String date) {
    return 'الانضمام قبل $date';
  }
}

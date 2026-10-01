BAFO — COMPLETE BRAND PACKAGE (English)
==========================================

01_brand_guide/
    BAFO_Brand_Guidelines.pdf   Single consolidated reference (14 pages):
                                logo system, full color system, logo
                                variations, tagline usage, app icon spec
                                (dark + light), in-app usage mockups
                                (home screen / splash screen / nav bar /
                                buttons), stationery, social media kit,
                                typography, vector file notes, presentation
                                template, brand voice guide.

02_logo_files/
    color/           Full-color icon + master app icon, transparent PNG.
    monochrome/       All-black and all-white single-color versions.
    arabic/            RTL Arabic wordmark ("بافو") and full lockups.
    vector-source/     icon_master.svg — editable vector source.

03_app_icons/
    dark/              Default variant — charcoal background.
    light/              Alternative variant — white background.
    Each contains:      ios/ (1024,180,152,120px), android/ (512,192,144,
                        96,72px + Adaptive Icon layers), web-favicon/
                        (192,60,48,32,16px + favicon.ico multi-res).
                        Note: the Android Adaptive foreground layer is
                        identical in both — only the background layer
                        and flat icon files differ between variants.

04_ui_color_tokens/
    bafo_colors.css    CSS custom properties — brand, gray scale, semantic
                       (success/error/warning/info), light & dark surfaces.
    bafo_colors.json   Same tokens as platform-agnostic JSON.

05_stationery/
    BAFO_Business_Card.pdf   89×51mm, front + back, print-ready.
    BAFO_Letterhead.pdf      A4, print-ready.
    BAFO_Envelope.pdf        DL (220×110mm), print-ready.
    email_signature_template.html + email_signature_icon_120.png
                             Table-based HTML signature. Host the icon on
                             your own domain and update the src URL before use.

06_social_media_kit/
    social_profile_picture_800.png       800×800, safe for circular crop.
    social_cover_linkedin_1584x396.png   LinkedIn banner size.
    social_cover_x_1500x500.png          X/Twitter banner size.

07_powerpoint_template/
    BAFO_PowerPoint_Template.pptx   5 layouts: title, section divider,
                                    content+stat card, line chart, closing.

IMPORTANT PRODUCTION NOTES
----------------------------
1. The "BAFO" wordmark throughout uses a system sans-serif fallback, not
   a licensed production typeface. Before final print or style-guide
   sign-off, re-set it in the chosen font (Neue Montreal / General Sans /
   Söhne / Inter — Extra Bold) and convert to outlines.

2. The Arabic wordmark is correctly shaped (RTL, proper letter joining)
   but set in a traditional serif Arabic style for the same reason —
   re-set in Cairo Bold, Tajawal Bold, or IBM Plex Sans Arabic Bold for
   production to match the Latin wordmark's geometric weight.

3. No true .ai/.eps files were generated — icon_master.svg is the closest
   editable source and imports cleanly into Illustrator or Figma.

4. App icon: the DARK variant is the recommended default for app stores
   and marketing (higher contrast at small sizes). The LIGHT variant is
   provided as a ready alternative — both share identical icon geometry
   and brand colors, so switching between them is a drop-in swap.

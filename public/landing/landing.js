(function(){
  // Sales contact, shown if the request cannot be sent.
  const CONTACT = "systems@awj.om";

  const T = {
    en:{
      nav_how:"How it works",nav_screens:"Screens",nav_brand:"Your brand",nav_plans:"Plans",nav_faq:"FAQ",
      cta_demo:"Book a demo",cta_how:"See how the wallet works",
      hero_eyebrow:"White-label event app for the Gulf",hero_h1a:"Your season.",hero_h1b:"Your app.",
      hero_lede:"Mawsim gives festivals, governorates, malls and expos their own branded app: tickets, a cashless wallet, cup and wristband deposits, and loyalty points. One platform, configured for you, not built from scratch.",
      fact_1:"Arabic-first, with English",fact_2:"Amounts in OMR to the baisa",fact_3:"Bank gateway built in",fact_4:"Many organizers, one platform",
      try_label:"Try a brand",try_note:"Same app, re-branded from settings. Tap Pay to see the vendor code.",
      p_hello:"Good evening, Salim",p_wallet:"Wallet",p_qr:"QR Pay",p_balance:"Wallet balance",p_points:"340 pts ≈ OMR 3.400",p_held:"1 cup deposit · OMR 0.500",p_pay:"Pay",p_top:"Top up",
      p_q1:"Top up",p_q2:"Pay",p_q3:"Tickets",p_q4:"Points",p_q5:"Map",p_ann:"Gates open at 4:00 pm. Fireworks at 9:30.",p_featured:"Featured",p_all:"See all",p_tag:"Opening night",p_mon:"DEC",
      p_n1:"Home",p_n2:"Explore",p_n3:"Tickets",p_n4:"More",p_pay_t:"Show this code to the vendor",p_avail:"Available to spend",p_demo:"Sample code for this preview. Real codes refresh every 60 seconds and work once.",
      j_eyebrow:"The visitor's evening",j_title:"One wallet for the whole visit",j_lede:"Visitors top up once. After that, every ticket, karak and cup deposit is a scan, and every rial spent earns points.",
      s1_t:"Top up",s1_p:"Card payment through your bank's gateway. Optional top-up bonuses arrive as separate promo credit.",
      s2_t:"Tickets",s2_p:"Priced by the server, paid from the wallet in one tap. The QR lands in the Tickets tab.",
      s3_t:"Stalls",s3_p:"The visitor shows a pay code. The vendor keys in the amount and scans. No cash, no change.",
      s4_t:"Deposits",s4_p:"Cups, wristbands and lockers are held from the balance and released when the item comes back.",
      s5_t:"Points",s5_p:"Spending earns points with tier multipliers. Points convert into wallet credit.",
      s6_t:"Refund",s6_p:"Unused cash goes back on request within your refund window. Promo credit stays promotional.",
      sc_eyebrow:"Real screens",sc_title:"Built and tested, not a concept",sc_lede:"These are screenshots of the working app, branded for a sample organizer. Nothing in this row is a mockup.",
      sh1_t:"Home",sh1_p:"Wallet up top, then announcements and what's on tonight.",sh2_t:"Pay code",sh2_p:"Signed, single use and refreshed every minute.",sh3_t:"Points and tiers",sh3_p:"Bronze, Silver, Gold, with expiry warnings and history.",sh4_t:"Tickets",sh4_p:"A large QR, ready for quick scanning at the gate.",
      v_eyebrow:"For vendors",v_title:"A till in every stall owner's phone",v_lede:"Stall staff sign in with their number and get a vendor mode: a keypad, a scanner and today's totals. You onboard vendors from the dashboard.",
      v_1:"Charge in two taps. Key in the amount, scan the visitor's code.",v_2:"Hold and return deposits. Cups and wristbands are released back to the wallet on drop-off.",v_3:"Settle with confidence. Every sale is on the ledger with sales, refunds and net per day.",
      b_eyebrow:"Your brand",b_title:"Configured, not coded",b_lede:"Change colors, logo and fonts in the branding editor and watch a live phone preview. The app checks every color for readability and adjusts shades that would be hard to read.",
      k1:"Colors and header style",k2:"Logo and launch screen",k3:"Arabic and English fonts",k4:"Which features are on",k5:"Tab order and home sections",k6:"Top-up limits and bonuses",k7:"Points rules and tiers",k8:"Refund window and fees",k9:"Country code and phone format",
      cap_brand:"Branding editor, sample organizer",cap_dash:"Dashboard, sample organizer",
      d_eyebrow:"For your team",d_title:"The control room for the season",d_lede:"Revenue, tickets, top-ups and money held in wallets, live. Approve refunds, adjust wallets with a reason on record, and give each team member only the access they need.",
      d_1:"Every rial accounted for. An append-only ledger separates cash, promo credit and held deposits.",d_2:"Several organizers, kept apart. Each organizer's events, wallets and staff stay separate, with one platform view on top.",d_3:"Arabic and English dashboard. Right-to-left done properly, with dark mode.",
      c_eyebrow:"Where Mawsim fits",c_title:"Ticketing apps sell entry. Cashless kits handle stalls. Mawsim does both, in your name.",
      c_h0:"What you need",c_h1:"Ticketing marketplaces",c_h2:"Cashless wristband systems",c_note:"Typical offerings in the GCC market, from our 2026 competitor review. Individual products vary.",
      yes:"Yes",part:"Partly",no:"No",
      r1:"An app in your own brand",r2:"Arabic-first app",r3:"Tickets with gate QR",r4:"Closed-loop wallet",r5:"Cup and wristband deposits",r6:"Loyalty points and tiers",r7:"Vendor payments at stalls",r8:"Refunds of unused balance",r9:"Many organizers on one platform",
      l_eyebrow:"Getting started",l_title:"From brand kit to opening night",
      l1_t:"Send your brand kit",l1_p:"Logo, colors, fonts and the features you want switched on.",l2_t:"Load the season",l2_p:"Events, prices, capacity, vendors and map points, all from the dashboard.",l3_t:"Your app in the stores",l3_p:"A build with your name, icon and launch screen, on iOS and Android.",l4_t:"Run the season",l4_p:"Gate scanning, vendor sales, refunds and daily totals, with support through the season.",
      pl_eyebrow:"Plans",pl_title:"Pick the shape of your season",
      pl1_t:"Season",pl1_w:"One festival or event season.",pl1_a:"Your branded app",pl1_b:"Tickets and gate scanning",pl1_c:"Wallet, top-ups and vendor payments",pl1_d:"Launch support",
      pl2_t:"Organizer",pl2_w:"Year-round events under one brand.",pl2_a:"Everything in Season",pl2_b:"Points, tiers and top-up bonuses",pl2_c:"Cup, wristband and locker deposits",pl2_d:"Vendor onboarding and settlement reports",
      pl3_t:"Governorate and enterprise",pl3_w:"Many organizers or wilayats on one platform.",pl3_a:"Separate brands and data per organizer",pl3_b:"Platform-wide view for your office",pl3_c:"Your own payment gateway account",pl3_d:"On-site operations support",
      price_b:"Priced as a share of ticket and wallet volume, with a minimum.",price_s:"No fixed per-ticket fee, so a 0.500 OMR entry ticket still makes sense.",
      f_eyebrow:"Questions",f_title:"What organizers ask us",
      q1:"Is the wallet regulated?",a1:"Stored value in Oman falls under the Central Bank of Oman. Mawsim keeps wallet credit within your events and takes payments through a licensed bank gateway. We review the setup with each organizer and recommend a legal check before launch.",
      q2:"Can visitors get their money back?",a2:"Yes. Unused cash can be refunded on request within the window you set, minus any fee you choose. Promo credit from bonuses or points is not refundable, and the app explains the difference before the visitor asks.",
      q3:"Do we get our own app in the stores?",a3:"Yes. Each organizer gets a build with its own name, icon, colors and launch screen. Colors, logo and content changes after launch apply from the dashboard without a new release.",
      q4:"Can one office run several events or wilayats?",a4:"Yes. Each organizer has its own events, wallets, vendors and staff, kept separate, and a platform admin can switch between them.",
      q5:"What do vendors need?",a5:"A smartphone with the app. Staff sign in with their phone number and get vendor mode with a keypad, a scanner and today's totals.",
      q6:"Is it really Arabic-first?",a6:"The app, the dashboard and the ticket pages are designed right-to-left first, with English alongside. Money uses Western digits to three decimals, the way OMR is written on receipts.",
      cta_eyebrow:"Book a demo",cta_title:"Bring Mawsim to your next season",cta_sub:"Tell us about your event. We'll show the app in your colors and walk through a full visit, from top-up to refund.",
      fm_name:"Your name",fm_org:"Organization",fm_type:"Type of event",fm_size:"Expected visitors",fm_when:"Season dates",fm_contact:"Phone or email",fm_notes:"Anything else",fm_small:"Takes a minute. No commitment.",fm_send:"Send my request",fm_sending:"Sending…",
      fm_done_t:"Request received",fm_done_c:"Thanks, we've received your request. We'll contact you at {c}.",fm_copy:"Copy summary",fm_copied:"Copied",fm_copy_fail:"Select the text above and copy it.",fm_again:"Send another request",
      fm_fail:"We couldn't send your request. Check your connection and try again, or email {c}.",fm_expired:"This page has expired. Reload it and send again.",
      fm_err:"Add your name, organization and a phone number or email so we can reach you.",
      types:["Festival","Exhibition or expo","Concert series","Mall season","Governorate or municipality","Sports event","Other"],
      sizes:["Under 5,000","5,000 – 25,000","25,000 – 100,000","More than 100,000"],
      sum:{name:"Name",org:"Organization",type:"Event type",size:"Expected visitors",when:"Season",contact:"Contact",notes:"Notes",head:"Mawsim demo request"},
      ft_by:"An events platform by AWJ Innovation, Oman.",ft_signin:"Staff sign in",lang_btn:"عربي"
    },
    ar:{
      nav_how:"كيف يعمل",nav_screens:"الشاشات",nav_brand:"هويتك",nav_plans:"الباقات",nav_faq:"الأسئلة",
      cta_demo:"احجز عرضاً",cta_how:"شاهد كيف تعمل المحفظة",
      hero_eyebrow:"تطبيق فعاليات بهويتك لمنطقة الخليج",hero_h1a:"موسمك.",hero_h1b:"تطبيقك.",
      hero_lede:"يمنح موسم المهرجانات والمحافظات والمراكز التجارية والمعارض تطبيقاً بهويتها: تذاكر، ومحفظة بلا نقد، وتأمينات الأكواب والأساور، ونقاط ولاء. منصة واحدة تُضبط لك، دون بناء من الصفر.",
      fact_1:"بالعربية أولاً، ومعها الإنجليزية",fact_2:"المبالغ بالريال العُماني حتى البيسة",fact_3:"بوابة دفع بنكية مدمجة",fact_4:"منظمون كُثر على منصة واحدة",
      try_label:"جرّب هوية",try_note:"التطبيق نفسه بهوية مختلفة من الإعدادات. اضغط «ادفع» لترى رمز البائع.",
      p_hello:"مساء الخير، سالم",p_wallet:"المحفظة",p_qr:"ادفع بالرمز",p_balance:"رصيد المحفظة",p_points:"340 نقطة ≈ OMR 3.400",p_held:"تأمين كوب · OMR 0.500",p_pay:"ادفع",p_top:"شحن",
      p_q1:"شحن",p_q2:"ادفع",p_q3:"تذاكري",p_q4:"النقاط",p_q5:"الخريطة",p_ann:"تُفتح البوابات 4:00 عصراً، والألعاب النارية 9:30.",p_featured:"فعاليات مميزة",p_all:"عرض الكل",p_tag:"ليلة الافتتاح",p_mon:"ديسمبر",
      p_n1:"الرئيسية",p_n2:"استكشف",p_n3:"تذاكري",p_n4:"المزيد",p_pay_t:"اعرض هذا الرمز على البائع",p_avail:"المتاح للإنفاق",p_demo:"رمز تجريبي لهذه المعاينة. الرموز الحقيقية تتجدد كل 60 ثانية وتُستخدم مرة واحدة.",
      j_eyebrow:"أمسية الزائر",j_title:"محفظة واحدة للزيارة كلها",j_lede:"يشحن الزائر مرة واحدة، وبعدها تصبح كل تذكرة وكوب كرك وتأمين مسحاً واحداً، وكل ريال يُنفق يكسب نقاطاً.",
      s1_t:"الشحن",s1_p:"دفع بالبطاقة عبر بوابة البنك. مكافآت الشحن الاختيارية تصل رصيداً ترويجياً منفصلاً.",
      s2_t:"التذاكر",s2_p:"السعر يحدده الخادم، والدفع من المحفظة بلمسة. يصل رمز التذكرة إلى تبويب تذاكري.",
      s3_t:"الأكشاك",s3_p:"يعرض الزائر رمز الدفع، ويُدخل البائع المبلغ ويمسح. لا نقد ولا فكّة.",
      s4_t:"التأمينات",s4_p:"تُحجز تأمينات الأكواب والأساور والخزائن من الرصيد وتُعاد عند إرجاع الغرض.",
      s5_t:"النقاط",s5_p:"الإنفاق يكسب نقاطاً بمضاعفات حسب المستوى، وتتحول النقاط إلى رصيد في المحفظة.",
      s6_t:"الاسترداد",s6_p:"يُعاد النقد غير المستخدم عند الطلب خلال مدة الاسترداد التي تحددها. الرصيد الترويجي يبقى ترويجياً.",
      sc_eyebrow:"شاشات حقيقية",sc_title:"مبني ومُختبر، وليس فكرة",sc_lede:"هذه لقطات من التطبيق الفعلي بهوية منظم تجريبي. لا شيء في هذا الصف تصميم وهمي.",
      sh1_t:"الرئيسية",sh1_p:"المحفظة في الأعلى، ثم الإعلانات وفعاليات الليلة.",sh2_t:"رمز الدفع",sh2_p:"موقّع، يُستخدم مرة واحدة ويتجدد كل دقيقة.",sh3_t:"النقاط والمستويات",sh3_p:"برونزي وفضي وذهبي، مع تنبيهات الانتهاء والسجل.",sh4_t:"التذاكر",sh4_p:"رمز كبير جاهز للمسح السريع عند البوابة.",
      v_eyebrow:"للبائعين",v_title:"صندوق دفع في هاتف كل صاحب كشك",v_lede:"يسجّل موظفو الأكشاك دخولهم برقم الهاتف ويحصلون على وضع البائع: لوحة أرقام وماسح وإجمالي اليوم. وتضيف البائعين من لوحة التحكم.",
      v_1:"تحصيل بلمستين: أدخل المبلغ وامسح رمز الزائر.",v_2:"حجز التأمينات وإعادتها: تعود قيمة الأكواب والأساور إلى المحفظة عند الإرجاع.",v_3:"تسوية بثقة: كل عملية بيع مسجلة في الدفتر مع المبيعات والمستردات والصافي لكل يوم.",
      b_eyebrow:"هويتك",b_title:"بالإعدادات، لا بالبرمجة",b_lede:"غيّر الألوان والشعار والخطوط من محرر الهوية وشاهد معاينة حية على الهاتف. يفحص التطبيق كل لون لضمان وضوح القراءة ويعدّل الدرجات التي يصعب قراءتها.",
      k1:"الألوان ونمط الترويسة",k2:"الشعار وشاشة الإطلاق",k3:"الخطوط العربية والإنجليزية",k4:"الميزات المفعّلة",k5:"ترتيب التبويبات وأقسام الرئيسية",k6:"حدود الشحن ومكافآته",k7:"قواعد النقاط والمستويات",k8:"مدة الاسترداد ورسومه",k9:"رمز الدولة وصيغة الهاتف",
      cap_brand:"محرر الهوية، منظم تجريبي",cap_dash:"لوحة التحكم، منظم تجريبي",
      d_eyebrow:"لفريقك",d_title:"غرفة التحكم للموسم كله",d_lede:"الإيرادات والتذاكر والشحن والأموال المحفوظة في المحافظ لحظة بلحظة. وافق على طلبات الاسترداد، وعدّل المحافظ مع تسجيل السبب، وامنح كل عضو في الفريق الصلاحية التي يحتاجها فقط.",
      d_1:"كل ريال محسوب: دفتر لا يُعدَّل يفصل النقد والرصيد الترويجي والتأمينات المحجوزة.",d_2:"منظمون كُثر بفصل تام: فعاليات كل منظم ومحافظه وموظفوه منفصلة، مع عرض شامل للمنصة.",d_3:"لوحة بالعربية والإنجليزية: اتجاه من اليمين إلى اليسار متقن، مع الوضع الداكن.",
      c_eyebrow:"أين يقع موسم",c_title:"تطبيقات التذاكر تبيع الدخول، وأنظمة الدفع بلا نقد تخدم الأكشاك. موسم يجمع الاثنين، باسمك.",
      c_h0:"ما تحتاجه",c_h1:"منصات بيع التذاكر",c_h2:"أنظمة الأساور بلا نقد",c_note:"العروض الشائعة في سوق الخليج، من مراجعتنا للمنافسين في 2026. تختلف المنتجات من واحد لآخر.",
      yes:"نعم",part:"جزئياً",no:"لا",
      r1:"تطبيق بهويتك الخاصة",r2:"تطبيق بالعربية أولاً",r3:"تذاكر برمز للبوابة",r4:"محفظة مغلقة",r5:"تأمينات الأكواب والأساور",r6:"نقاط ولاء ومستويات",r7:"الدفع لدى الأكشاك",r8:"استرداد الرصيد غير المستخدم",r9:"منظمون كُثر على منصة واحدة",
      l_eyebrow:"البداية",l_title:"من حزمة الهوية إلى ليلة الافتتاح",
      l1_t:"أرسل حزمة هويتك",l1_p:"الشعار والألوان والخطوط والميزات التي تريد تفعيلها.",l2_t:"جهّز الموسم",l2_p:"الفعاليات والأسعار والسعة والبائعون ونقاط الخريطة، كلها من لوحة التحكم.",l3_t:"تطبيقك في المتاجر",l3_p:"نسخة باسمك وأيقونتك وشاشة إطلاقك على iOS وأندرويد.",l4_t:"شغّل الموسم",l4_p:"مسح البوابات ومبيعات البائعين والاسترداد والإجماليات اليومية، مع دعم طوال الموسم.",
      pl_eyebrow:"الباقات",pl_title:"اختر شكل موسمك",
      pl1_t:"موسم",pl1_w:"مهرجان واحد أو موسم فعاليات.",pl1_a:"تطبيق بهويتك",pl1_b:"التذاكر ومسح البوابات",pl1_c:"المحفظة والشحن والدفع للبائعين",pl1_d:"دعم الإطلاق",
      pl2_t:"منظِّم",pl2_w:"فعاليات على مدار العام تحت هوية واحدة.",pl2_a:"كل ما في باقة موسم",pl2_b:"النقاط والمستويات ومكافآت الشحن",pl2_c:"تأمينات الأكواب والأساور والخزائن",pl2_d:"تسجيل البائعين وتقارير التسوية",
      pl3_t:"المحافظات والمؤسسات",pl3_w:"منظمون أو ولايات متعددة على منصة واحدة.",pl3_a:"هوية وبيانات منفصلة لكل منظم",pl3_b:"عرض شامل للمنصة لمكتبك",pl3_c:"حساب بوابة دفع خاص بك",pl3_d:"دعم تشغيلي في الموقع",
      price_b:"التسعير نسبة من قيمة التذاكر والمحفظة، مع حد أدنى.",price_s:"لا رسوم ثابتة على كل تذكرة، فتبقى تذكرة دخول بـ 0.500 ريال منطقية.",
      f_eyebrow:"أسئلة",f_title:"ما يسألنا عنه المنظمون",
      q1:"هل المحفظة خاضعة للتنظيم؟",a1:"تخضع القيمة المخزنة في عُمان لإشراف البنك المركزي العُماني. يبقي موسم رصيد المحفظة ضمن فعالياتك ويستقبل المدفوعات عبر بوابة بنكية مرخصة. نراجع الإعداد مع كل منظم ونوصي بمراجعة قانونية قبل الإطلاق.",
      q2:"هل يستطيع الزوار استرداد أموالهم؟",a2:"نعم. يمكن استرداد النقد غير المستخدم عند الطلب خلال المدة التي تحددها، بعد خصم أي رسوم تختارها. الرصيد الترويجي من المكافآت أو النقاط غير قابل للاسترداد، ويوضح التطبيق الفرق قبل أن يسأل الزائر.",
      q3:"هل نحصل على تطبيقنا الخاص في المتاجر؟",a3:"نعم. لكل منظم نسخة باسمه وأيقونته وألوانه وشاشة إطلاقه. وتُطبَّق تغييرات الألوان والشعار والمحتوى بعد الإطلاق من لوحة التحكم دون إصدار جديد.",
      q4:"هل يستطيع مكتب واحد إدارة فعاليات أو ولايات متعددة؟",a4:"نعم. لكل منظم فعالياته ومحافظه وبائعوه وموظفوه بشكل منفصل، ويستطيع مدير المنصة التنقل بينهم.",
      q5:"ماذا يحتاج البائعون؟",a5:"هاتفاً ذكياً عليه التطبيق. يسجّل الموظفون دخولهم برقم الهاتف ويحصلون على وضع البائع مع لوحة أرقام وماسح وإجمالي اليوم.",
      q6:"هل هو بالعربية أولاً فعلاً؟",a6:"صُمم التطبيق ولوحة التحكم وصفحات التذاكر من اليمين إلى اليسار أولاً، ومعها الإنجليزية. وتُكتب المبالغ بالأرقام الغربية حتى ثلاث خانات عشرية، كما يُكتب الريال العُماني في الإيصالات.",
      cta_eyebrow:"احجز عرضاً",cta_title:"اجلب موسم إلى موسمك القادم",cta_sub:"أخبرنا عن فعاليتك، وسنعرض لك التطبيق بألوانك ونمر معك بزيارة كاملة، من الشحن حتى الاسترداد.",
      fm_name:"اسمك",fm_org:"الجهة",fm_type:"نوع الفعالية",fm_size:"عدد الزوار المتوقع",fm_when:"تواريخ الموسم",fm_contact:"الهاتف أو البريد",fm_notes:"ملاحظات أخرى",fm_small:"دقيقة واحدة، دون أي التزام.",fm_send:"أرسل طلبي",fm_sending:"جارٍ الإرسال…",
      fm_done_t:"تم استلام طلبك",fm_done_c:"شكراً، استلمنا طلبك. سنتواصل معك على {c}.",fm_copy:"انسخ الملخص",fm_copied:"تم النسخ",fm_copy_fail:"حدد النص أعلاه وانسخه.",fm_again:"أرسل طلباً آخر",
      fm_fail:"تعذّر إرسال طلبك. تحقق من اتصالك وحاول مرة أخرى، أو راسلنا على {c}.",fm_expired:"انتهت صلاحية الصفحة. أعد تحميلها ثم أرسل الطلب.",
      fm_err:"أضف اسمك والجهة ورقم هاتف أو بريداً لنتواصل معك.",
      types:["مهرجان","معرض","سلسلة حفلات","موسم مركز تجاري","محافظة أو بلدية","فعالية رياضية","أخرى"],
      sizes:["أقل من 5,000","5,000 – 25,000","25,000 – 100,000","أكثر من 100,000"],
      sum:{name:"الاسم",org:"الجهة",type:"نوع الفعالية",size:"الزوار المتوقعون",when:"الموسم",contact:"التواصل",notes:"ملاحظات",head:"طلب عرض موسم"},
      ft_by:"منصة فعاليات من أوج للابتكار، عُمان.",ft_signin:"دخول فريق العمل",lang_btn:"English"
    }
  };

  // Example organizers (fictional) for the live preview.
  const TENANTS = [
    {a:"#127A6D",b:"#2C7DA5",p:"#127A6D",soft:"#E1F1EE",font:"Readex Pro",logo:"H",name:{en:"Harbour Lights Festival",ar:"مهرجان أضواء الميناء"},ev:{en:"Opening Night on the Corniche",ar:"ليلة الافتتاح على الكورنيش"},bal:"12.450"},
    {a:"#4F46E5",b:"#7C3AED",p:"#4F46E5",soft:"#ECEBFD",font:"Readex Pro",logo:"C",name:{en:"City Expo",ar:"معرض المدينة"},ev:{en:"Future Mobility Summit",ar:"قمة مستقبل التنقل"},bal:"24.750"},
    {a:"#9C4A14",b:"#D0892C",p:"#9C4A14",soft:"#F8EADB",font:"Readex Pro",logo:"S",name:{en:"Old Souq Season",ar:"موسم السوق القديم"},ev:{en:"Night Market and Crafts",ar:"السوق الليلي والحرف"},bal:"8.200"},
    {a:"#A31D3A",b:"#5E1430",p:"#A31D3A",soft:"#F7E3E8",font:"Readex Pro",logo:"D",name:{en:"Desert Concert Series",ar:"سلسلة حفلات الصحراء"},ev:{en:"Sunset Strings in the Dunes",ar:"أوتار الغروب بين الكثبان"},bal:"31.000"}
  ];

  const CMP = [
    ["r1","n","y","y"],["r2","p","n","y"],["r3","y","p","y"],["r4","n","y","y"],["r5","n","y","y"],["r6","p","p","y"],["r7","n","y","y"],["r8","n","y","y"],["r9","y","p","y"]
  ];

  const $ = (s)=>document.querySelector(s);
  // Language: ?lang= in the URL, then the saved choice, then what the server rendered.
  let lang = document.documentElement.lang==="ar" ? "ar" : "en", tIdx = 0;
  const urlLang = new URLSearchParams(location.search).get("lang");
  try{ const s = localStorage.getItem("mawsim_lang"); if(!(urlLang==="ar"||urlLang==="en") && (s==="ar"||s==="en")) lang = s; }catch(e){}
  function saveLang(){
    try{ localStorage.setItem("mawsim_lang",lang); }catch(e){}
    document.cookie = "mawsim_lang="+lang+";path=/;max-age=31536000;samesite=lax";
    if(urlLang){ try{ const u=new URL(location.href); u.searchParams.set("lang",lang); history.replaceState(null,"",u); }catch(e){} }
  }
  if(urlLang==="ar"||urlLang==="en") saveLang();

  function render(){
    const d = T[lang];
    document.documentElement.lang = lang;
    document.documentElement.dir = lang==="ar" ? "rtl" : "ltr";
    document.querySelectorAll("[data-i18n]").forEach(el=>{
      const k = el.getAttribute("data-i18n");
      const v = d[k]; if(typeof v!=="string") return;
      const dot = v.indexOf(". ");
      // Tick items: bold lead sentence, then the rest.
      if(/^(v_|d_)\d$/.test(k) && dot>0){
        el.textContent=""; const b=document.createElement("b"); b.textContent=v.slice(0,dot+1)+" "; el.append(b, v.slice(dot+2));
      } else if(/^(v_|d_)\d$/.test(k) && v.indexOf(": ")>0){
        const i=v.indexOf(": "); el.textContent=""; const b=document.createElement("b"); b.textContent=v.slice(0,i+1)+" "; el.append(b, v.slice(i+2));
      } else el.textContent = v;
    });
    $("#langBtn").textContent = d.lang_btn;
    $("#langBtn").setAttribute("aria-label", lang==="ar" ? "Switch to English" : "التبديل إلى العربية");
    // compare table
    const body = $("#cmpBody"); body.textContent = "";
    CMP.forEach(row=>{
      const tr=document.createElement("tr");
      const th=document.createElement("th"); th.scope="row"; th.textContent=d[row[0]]; th.style.fontWeight="500"; tr.append(th);
      row.slice(1).forEach((m,i)=>{ const td=document.createElement("td"); if(i===2) td.className="us"; const s=document.createElement("span"); s.className="mark "+m; s.textContent = m==="y"?d.yes:m==="p"?d.part:d.no; td.append(s); tr.append(td); });
      body.append(tr);
    });
    // selects (keep chosen index)
    fillSelect($("#fType"), TYPE_KEYS, d.types); fillSelect($("#fSize"), SIZE_KEYS, d.sizes);
    $("#fLocale").value = lang;
    const dc=$("#fDoneP").getAttribute("data-contact"); if(dc) setDoneText(dc);
    renderSwatches(); applyTenant(false);
  }
  // Options submit stable keys; labels follow the language (keep the chosen key).
  const TYPE_KEYS=["festival","expo","concerts","mall","governorate","sports","other"], SIZE_KEYS=["lt5k","5k-25k","25k-100k","gt100k"];
  function fillSelect(sel, keys, labels){ const v = keys.includes(sel.value) ? sel.value : keys[0]; sel.textContent=""; keys.forEach((k,i)=>{ const op=document.createElement("option"); op.value=k; op.textContent=labels[i]; sel.append(op); }); sel.value = v; }

  function renderSwatches(){
    const box = $("#swatches"); box.textContent="";
    TENANTS.forEach((t,i)=>{
      const b=document.createElement("button"); b.type="button"; b.className="swatch"; b.setAttribute("aria-pressed", String(i===tIdx));
      const dot=document.createElement("i"); dot.style.setProperty("--a",t.a); dot.style.setProperty("--b",t.b);
      b.append(dot, t.name[lang]); b.addEventListener("click",()=>{ tIdx=i; renderSwatches(); applyTenant(true); });
      box.append(b);
    });
  }
  function applyTenant(animate){
    const t = TENANTS[tIdx], ph = $("#phone");
    ph.style.setProperty("--t1",t.a); ph.style.setProperty("--t2",t.b); ph.style.setProperty("--tp",t.p); ph.style.setProperty("--tsoft",t.soft);
    $("#pLogo").textContent = t.logo; $("#pOrg").textContent = t.name[lang]; $("#pEv").textContent = t.ev[lang];
    ph.setAttribute("aria-label", (lang==="ar"?"معاينة حية للتطبيق: ":"Live preview of the app: ")+t.name[lang]);
    countTo(t.bal, animate);
    drawLights(t.a, t.b);
  }
  let rafId=0;
  function countTo(target, animate){
    const el=$("#pBal"), to=parseFloat(target), from=parseFloat(el.textContent)||0;
    cancelAnimationFrame(rafId);
    const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if(!animate || reduce){ el.textContent=to.toFixed(3); return; }
    const t0=performance.now(), dur=650;
    const step=(now)=>{ const k=Math.min(1,(now-t0)/dur), e=1-Math.pow(1-k,3); el.textContent=(from+(to-from)*e).toFixed(3); if(k<1) rafId=requestAnimationFrame(step); };
    rafId=requestAnimationFrame(step);
  }

  // String lights across the hero, tinted by the selected organizer.
  function drawLights(c1, c2){
    const svg=$("#lights"); const NS="http://www.w3.org/2000/svg"; svg.textContent="";
    const defs=document.createElementNS(NS,"defs");
    defs.innerHTML='<filter id="glow" x="-200%" y="-200%" width="500%" height="500%"><feGaussianBlur stdDeviation="4"/></filter>';
    svg.append(defs);
    const strands=[{y0:18,sag:70,n:26},{y0:6,sag:110,n:30}];
    const palette=["#F2B33D",c1,"#FFE9B8",c2];
    strands.forEach((s,si)=>{
      const wire=document.createElementNS(NS,"path");
      wire.setAttribute("d",`M0 ${s.y0} Q600 ${s.y0+s.sag*2} 1200 ${s.y0}`); wire.setAttribute("fill","none"); wire.setAttribute("stroke","rgba(255,255,255,.14)"); wire.setAttribute("stroke-width","1");
      svg.append(wire);
      for(let i=0;i<=s.n;i++){
        const t=i/s.n, x=1200*t, y=(1-t)*(1-t)*s.y0+2*(1-t)*t*(s.y0+s.sag*2)+t*t*s.y0;
        const col=palette[(i+si)%palette.length];
        const g=document.createElementNS(NS,"circle"); g.setAttribute("cx",x); g.setAttribute("cy",y+6); g.setAttribute("r","7"); g.setAttribute("fill",col); g.setAttribute("opacity",".45"); g.setAttribute("filter","url(#glow)");
        const b=document.createElementNS(NS,"circle"); b.setAttribute("cx",x); b.setAttribute("cy",y+6); b.setAttribute("r","2.6"); b.setAttribute("fill",col);
        const delay=((i*37+si*11)%23)/10;
        g.style.animation=`twinkle 3.2s ${delay}s ease-in-out infinite`;
        svg.append(g,b);
      }
    });
  }
  const tw=document.createElement("style"); tw.textContent="@keyframes twinkle{0%,100%{opacity:.45}50%{opacity:.15}}"; document.head.append(tw);

  // Pay code preview: a sample QR-style pattern with a countdown.
  let timer=0;
  function drawCode(){
    const cv=$("#qr"), ctx=cv.getContext("2d"), N=29; ctx.fillStyle="#fff"; ctx.fillRect(0,0,N,N); ctx.fillStyle="#0F1D24";
    let seed=Date.now()%100000; const rnd=()=>{ seed=(seed*16807)%2147483647; return seed/2147483647; };
    for(let y=0;y<N;y++) for(let x=0;x<N;x++){ if(rnd()>.52) ctx.fillRect(x,y,1,1); }
    const finder=(fx,fy)=>{ ctx.fillStyle="#fff"; ctx.fillRect(fx-1,fy-1,9,9); ctx.fillStyle="#0F1D24"; ctx.fillRect(fx,fy,7,7); ctx.fillStyle="#fff"; ctx.fillRect(fx+1,fy+1,5,5); ctx.fillStyle="#0F1D24"; ctx.fillRect(fx+2,fy+2,3,3); };
    finder(1,1); finder(N-8,1); finder(1,N-8);
  }
  function openPay(){
    $("#pPayView").hidden=false; drawCode(); let s=60; $("#pCount").textContent="0:60";
    clearInterval(timer); timer=setInterval(()=>{ s--; if(s<=0){ s=60; drawCode(); } $("#pCount").textContent="0:"+String(s).padStart(2,"0"); },1000);
    $("#pClose").focus();
  }
  function closePay(){ $("#pPayView").hidden=true; clearInterval(timer); $("#pPay").focus(); }
  $("#pPay").addEventListener("click",openPay);
  $("#pClose").addEventListener("click",closePay);
  $("#pTop").addEventListener("click",()=>{ document.getElementById("journey").scrollIntoView({behavior:"smooth"}); });

  $("#langBtn").addEventListener("click",()=>{ lang = lang==="en"?"ar":"en"; saveLang(); render(); });

  // Demo request: sent to the server (JSON); falls back to a normal POST without JS.
  const form=$("#demoForm"), sendBtn=$("#fSend");
  const csrf=(document.querySelector('meta[name="csrf-token"]')||{}).content||"";
  const FIELD_IDS={name:"fName",organization:"fOrg",event_type:"fType",expected_visitors:"fSize",season:"fWhen",contact:"fContact",notes:"fNotes"};
  function showErr(msg, key){
    const err=$("#fErr"); err.textContent=msg; err.hidden=false;
    form.querySelectorAll("[aria-invalid]").forEach(el=>el.removeAttribute("aria-invalid"));
    const el=key&&FIELD_IDS[key]?document.getElementById(FIELD_IDS[key]):null;
    if(el){ el.setAttribute("aria-invalid","true"); el.focus(); }
  }
  // Contact (often a +968 number) stays left-to-right inside Arabic text.
  function setDoneText(contact){
    const p=$("#fDoneP"), parts=T[lang].fm_done_c.split("{c}"), b=document.createElement("bdi");
    b.dir="ltr"; b.textContent=contact; p.textContent=""; p.append(parts[0], b, parts[1]||"");
  }
  function showDone(contact, summary){
    $("#fSummary").textContent=summary;
    $("#fDoneP").setAttribute("data-contact",contact);
    setDoneText(contact);
    form.querySelectorAll(".field").forEach(el=>el.hidden=true); $("#fFoot").hidden=true; $("#fErr").hidden=true; $("#fDone").hidden=false;
    $("#fDone").setAttribute("tabindex","-1"); $("#fDone").focus();
  }
  let sending=false;
  form.addEventListener("submit",async (e)=>{
    e.preventDefault();
    if(sending) return;
    const d=T[lang], f=new FormData(form), err=$("#fErr");
    const name=(f.get("name")||"").trim(), org=(f.get("organization")||"").trim(), contact=(f.get("contact")||"").trim();
    if(!name||!org||!contact){ showErr(d.fm_err, !name?"name":!org?"organization":"contact"); return; }
    err.hidden=true;
    const s=d.sum, lines=[s.head,"",`${s.name}: ${name}`,`${s.org}: ${org}`,`${s.type}: ${$("#fType").selectedOptions[0].textContent}`,`${s.size}: ${$("#fSize").selectedOptions[0].textContent}`];
    const when=(f.get("season")||"").trim(), notes=(f.get("notes")||"").trim();
    if(when) lines.push(`${s.when}: ${when}`); lines.push(`${s.contact}: ${lang==="ar"?"\u2066"+contact+"\u2069":contact}`); if(notes) lines.push(`${s.notes}: ${notes}`);
    f.set("locale",lang);
    const payload={}; f.forEach((v,k)=>{ if(k!=="_token") payload[k]=typeof v==="string"?v.trim():v; });
    sending=true; sendBtn.disabled=true; sendBtn.textContent=d.fm_sending;
    try{
      const res=await fetch(form.action,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json","X-CSRF-TOKEN":csrf,"X-Requested-With":"XMLHttpRequest"},body:JSON.stringify(payload)});
      const data=await res.json().catch(()=>({}));
      if(res.ok && data.success){ showDone(contact, lines.join("\n")); }
      else if(res.status===419){ showErr(d.fm_expired); }
      else if(data && data.message){ const keys=Object.keys(data.errors||{}); showErr(data.message, keys[0]); }
      else showErr(d.fm_fail.replace("{c}",CONTACT));
    }catch(ex){ showErr(d.fm_fail.replace("{c}",CONTACT)); }
    finally{ sending=false; sendBtn.disabled=false; sendBtn.textContent=T[lang].fm_send; }
  });
  const copyBtn=$("#fCopy"); if(navigator.clipboard||document.execCommand) copyBtn.hidden=false;
  copyBtn.addEventListener("click",()=>{
    const txt=$("#fSummary").textContent, msg=$("#fCopied"), d=T[lang];
    const fallback=()=>{ const r=document.createRange(); r.selectNodeContents($("#fSummary")); const sel=getSelection(); sel.removeAllRanges(); sel.addRange(r); msg.textContent=d.fm_copy_fail; msg.hidden=false; };
    try{ navigator.clipboard.writeText(txt).then(()=>{ msg.textContent=d.fm_copied; msg.hidden=false; }, fallback); }catch(e){ fallback(); }
  });
  $("#fEdit").addEventListener("click",(e)=>{
    e.preventDefault(); form.reset(); $("#fLocale").value=lang; $("#fDoneP").removeAttribute("data-contact");
    form.querySelectorAll(".field").forEach(el=>el.hidden=false); $("#fFoot").hidden=false; $("#fDone").hidden=true; $("#fCopied").hidden=true; $("#fErr").hidden=true; $("#fName").focus();
  });

  $("#yr").textContent = new Date().getFullYear();
  render();
})();

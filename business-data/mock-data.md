import '../../models/heritage_village_model.dart';

class MockHeritageData {
  static final List<HeritageVillage> villages = [
    HeritageVillage(
      id: 'maritime-village',
      nameEn: 'Maritime Heritage Village',
      nameAr: 'قرية التراث البحري',
      descriptionEn: 'Explore Oman\'s rich maritime history through traditional dhow building, pearl diving demonstrations, and coastal life exhibits.',
      descriptionAr: 'استكشف تاريخ عُمان البحري الغني من خلال بناء السفن التقليدية وعروض الغوص على اللؤلؤ ومعارض الحياة الساحلية.',
      type: VillageType.maritime,
      coverImage: 'assets/fastival_images/Yousuf__A_bustling_sunset_scene_of_the_Sohar_Agricultural_30b63713-e228-4682-87a7-edbd807f67d7_upscaled.png',
      imageGallery: [
        'assets/fastival_images/Yousuf__A_bustling_sunset_scene_of_the_Sohar_Agricultural_30b63713-e228-4682-87a7-edbd807f67d7_upscaled.png',
        'assets/fastival_images/Yousuf__A_night-time_human-perspective_scene_set_inside_a_01d2e2be-b40c-4f10-9fb0-9d48f8567339.png',
        'assets/fastival_images/Unknown-scaled.jpeg',
        'assets/fastival_images/festival_image_oman_website_2.avif',
      ],
      keyAttractionsEn: [
        'Traditional Dhow Building Workshop',
        'Pearl Diving Experience',
        'Fishing Net Weaving Demonstrations',
        'Maritime Museum Exhibition',
        'Sailor\'s Life Recreation',
      ],
      keyAttractionsAr: [
        'ورشة بناء السفن التقليدية',
        'تجربة الغوص على اللؤلؤ',
        'عروض نسج شباك الصيد',
        'معرض المتحف البحري',
        'إعادة تمثيل حياة البحارة',
      ],
      openingHours: '9:00 AM - 10:00 PM',
      craftDemonstrations: [
        CraftDemonstration(
          id: 'dhow-building',
          titleEn: 'Traditional Dhow Building',
          titleAr: 'بناء السفن التقليدية',
          descriptionEn: 'Watch master craftsmen build traditional Omani dhows using time-honored techniques.',
          descriptionAr: 'شاهد الحرفيين المهرة وهم يبنون السفن العمانية التقليدية باستخدام تقنيات عريقة.',
          craftspersonName: 'Master Abdullah Al-Sinawi',
          imageUrl: 'assets/fastival_images/Unknown-scaled.jpeg',
          scheduleTimes: ['10:00 AM', '2:00 PM', '6:00 PM'],
          durationMinutes: 45,
        ),
        CraftDemonstration(
          id: 'net-weaving',
          titleEn: 'Fishing Net Weaving',
          titleAr: 'نسج شباك الصيد',
          descriptionEn: 'Learn the ancient art of weaving fishing nets by hand.',
          descriptionAr: 'تعلم الفن القديم لنسج شباك الصيد يدوياً.',
          craftspersonName: 'Salim Al-Harthy',
          imageUrl: 'assets/fastival_images/Yousuf__A_night-time_human-perspective_scene_set_inside_a_01d2e2be-b40c-4f10-9fb0-9d48f8567339.png',
          scheduleTimes: ['11:00 AM', '3:00 PM', '7:00 PM'],
          durationMinutes: 30,
        ),
      ],
      activities: [
        TraditionalActivity(
          id: 'pearl-diving-demo',
          nameEn: 'Pearl Diving Experience',
          nameAr: 'تجربة الغوص على اللؤلؤ',
          descriptionEn: 'Experience the traditional pearl diving techniques in a safe environment.',
          descriptionAr: 'جرب تقنيات الغوص التقليدية على اللؤلؤ في بيئة آمنة.',
          imageUrl: 'assets/fastival_images/festival_image_oman_website_2.avif',
          isInteractive: true,
          ageRecommendation: '12+',
          timing: 'Every 2 hours',
        ),
        TraditionalActivity(
          id: 'boat-rowing',
          nameEn: 'Traditional Boat Rowing',
          nameAr: 'التجديف التقليدي',
          descriptionEn: 'Try your hand at rowing traditional Omani boats.',
          descriptionAr: 'جرب التجديف في القوارب العمانية التقليدية.',
          imageUrl: 'assets/fastival_images/66e9818dbaaf4.jpeg',
          isInteractive: true,
          ageRecommendation: 'All ages',
          timing: 'Continuous',
        ),
      ],
      workshops: [
        CulturalWorkshop(
          id: 'navigation-workshop',
          titleEn: 'Ancient Navigation Techniques',
          titleAr: 'تقنيات الملاحة القديمة',
          descriptionEn: 'Learn how Omani sailors navigated using stars and traditional instruments.',
          descriptionAr: 'تعلم كيف كان البحارة العمانيون يبحرون باستخدام النجوم والأدوات التقليدية.',
          instructorName: 'Captain Mohammed Al-Balushi',
          imageUrl: 'assets/fastival_images/Screenshot-2024-08-15-080741.jpg',
          scheduleTimes: ['5:00 PM', '8:00 PM'],
          durationMinutes: 60,
          maxParticipants: 20,
          priceOMR: 5.0,
          skillLevel: 'Beginner',
        ),
      ],
      photoSpots: [
        PhotoSpot(
          id: 'dhow-sunset',
          nameEn: 'Dhow at Sunset',
          nameAr: 'السفينة عند الغروب',
          descriptionEn: 'Capture stunning photos with traditional dhows against the sunset.',
          descriptionAr: 'التقط صوراً مذهلة للسفن التقليدية مع غروب الشمس.',
          imageUrl: 'assets/fastival_images/1705044621-1705044621-dlnp1cqjz6ev.jpeg',
          bestTimeForPhotos: 'Golden hour (5:30 PM - 6:30 PM)',
          photographyTips: [
            'Use the dhow\'s silhouette for dramatic effect',
            'Include reflections in the water',
            'Try different angles from the pier',
          ],
        ),
      ],
    ),
    HeritageVillage(
      id: 'agricultural-village',
      nameEn: 'Agricultural Heritage Village',
      nameAr: 'قرية التراث الزراعي',
      descriptionEn: 'Discover Oman\'s agricultural traditions, falaj irrigation systems, and date palm cultivation.',
      descriptionAr: 'اكتشف التقاليد الزراعية العمانية ونظم الري بالأفلاج وزراعة النخيل.',
      type: VillageType.agricultural,
      coverImage: 'assets/fastival_images/Yousuf__A_bustling_sunset_scene_of_the_Sohar_Agricultural_30b63713-e228-4682-87a7-edbd807f67d7_upscaled.png',
      imageGallery: [
        'assets/fastival_images/Yousuf__A_bustling_sunset_scene_of_the_Sohar_Agricultural_30b63713-e228-4682-87a7-edbd807f67d7_upscaled.png',
        'assets/fastival_images/Yousuf__A_magical_night-time_culinary_experience_set_in_a_64e8cfd2-0173-48e4-a36a-5b8056f25085_upscaled.png',
        'assets/fastival_images/Yousuf__A_night-time_human-scale_outdoor_market_scene_tit_26ee8e3b-37a6-4eb7-93be-a4301fa153fc_upscaled.png',
        'assets/fastival_images/IMG_5073.JPG',
      ],
      keyAttractionsEn: [
        'Traditional Falaj Irrigation System',
        'Date Palm Plantation',
        'Traditional Farming Tools Exhibition',
        'Organic Garden',
        'Agricultural Products Market',
      ],
      keyAttractionsAr: [
        'نظام الري بالأفلاج التقليدي',
        'مزرعة النخيل',
        'معرض أدوات الزراعة التقليدية',
        'الحديقة العضوية',
        'سوق المنتجات الزراعية',
      ],
      openingHours: '9:00 AM - 10:00 PM',
      craftDemonstrations: [
        CraftDemonstration(
          id: 'date-processing',
          titleEn: 'Date Processing & Preservation',
          titleAr: 'معالجة وحفظ التمور',
          descriptionEn: 'Learn traditional methods of processing and preserving dates.',
          descriptionAr: 'تعلم الطرق التقليدية لمعالجة وحفظ التمور.',
          craftspersonName: 'Fatima Al-Wahaibi',
          imageUrl: 'assets/fastival_images/Yousuf__A_magical_night-time_culinary_experience_set_in_a_64e8cfd2-0173-48e4-a36a-5b8056f25085_upscaled.png',
          scheduleTimes: ['10:30 AM', '3:30 PM', '6:30 PM'],
          durationMinutes: 40,
        ),
        CraftDemonstration(
          id: 'basket-weaving',
          titleEn: 'Palm Frond Basket Weaving',
          titleAr: 'نسج السلال من سعف النخيل',
          descriptionEn: 'Watch artisans create beautiful baskets from palm fronds.',
          descriptionAr: 'شاهد الحرفيين وهم يصنعون سلالاً جميلة من سعف النخيل.',
          craftspersonName: 'Maryam Al-Busaidi',
          imageUrl: 'assets/fastival_images/Unknown-scaled.jpeg',
          scheduleTimes: ['11:30 AM', '4:30 PM', '7:30 PM'],
          durationMinutes: 45,
        ),
      ],
      activities: [
        TraditionalActivity(
          id: 'falaj-tour',
          nameEn: 'Falaj System Tour',
          nameAr: 'جولة في نظام الأفلاج',
          descriptionEn: 'Guided tour of the traditional irrigation system.',
          descriptionAr: 'جولة مع مرشد في نظام الري التقليدي.',
          imageUrl: 'assets/fastival_images/Yousuf__A_night-time_human-scale_outdoor_market_scene_tit_26ee8e3b-37a6-4eb7-93be-a4301fa153fc_upscaled.png',
          isInteractive: false,
          ageRecommendation: 'All ages',
          timing: 'Every hour',
        ),
        TraditionalActivity(
          id: 'date-tasting',
          nameEn: 'Traditional Date Varieties Tasting',
          nameAr: 'تذوق أصناف التمور التقليدية',
          descriptionEn: 'Sample different varieties of Omani dates.',
          descriptionAr: 'تذوق أصناف مختلفة من التمور العمانية.',
          imageUrl: 'assets/fastival_images/IMG_5073.JPG',
          isInteractive: true,
          ageRecommendation: 'All ages',
          timing: 'Continuous',
        ),
      ],
      workshops: [
        CulturalWorkshop(
          id: 'organic-farming',
          titleEn: 'Traditional Organic Farming',
          titleAr: 'الزراعة العضوية التقليدية',
          descriptionEn: 'Learn about traditional Omani farming techniques and organic practices.',
          descriptionAr: 'تعلم عن تقنيات الزراعة العمانية التقليدية والممارسات العضوية.',
          instructorName: 'Ahmed Al-Kindi',
          imageUrl: 'assets/fastival_images/festival_image_oman_website_2.avif',
          scheduleTimes: ['10:00 AM', '4:00 PM'],
          durationMinutes: 90,
          maxParticipants: 15,
          priceOMR: 7.0,
          skillLevel: 'All levels',
        ),
      ],
      photoSpots: [
        PhotoSpot(
          id: 'date-palm-grove',
          nameEn: 'Date Palm Grove',
          nameAr: 'بستان النخيل',
          descriptionEn: 'Beautiful palm grove perfect for photography.',
          descriptionAr: 'بستان نخيل جميل مثالي للتصوير.',
          imageUrl: 'assets/fastival_images/66e9818dbaaf4.jpeg',
          bestTimeForPhotos: 'Early morning (7:00 AM - 9:00 AM)',
          photographyTips: [
            'Capture the morning light filtering through palm fronds',
            'Use leading lines created by irrigation channels',
            'Include traditional farming tools for context',
          ],
        ),
      ],
    ),
    HeritageVillage(
      id: 'bedouin-village',
      nameEn: 'Bedouin Heritage Village',
      nameAr: 'قرية التراث البدوي',
      descriptionEn: 'Experience the nomadic desert life, traditional hospitality, and Bedouin crafts.',
      descriptionAr: 'عش تجربة الحياة البدوية الصحراوية والضيافة التقليدية والحرف البدوية.',
      type: VillageType.bedouin,
      coverImage: 'assets/fastival_images/Yousuf__A_captivating_night-time_scene_featuring_an_open_0f73f5ee-9760-4235-b0f2-5ab2127d5762.png',
      imageGallery: [
        'assets/fastival_images/Yousuf__A_captivating_night-time_scene_featuring_an_open_0f73f5ee-9760-4235-b0f2-5ab2127d5762.png',
        'assets/fastival_images/Yousuf__A_realistic,_night-time_human-perspective_scene_o_68925e92-1c7c-4b24-82c1-f64cf1bc3a0a_upscaled.png',
        'assets/fastival_images/Yousuf__A_realistic_night-time_scene_at_a_festival_where_a4adb264-ab78-429a-bb0f-25d9d8f65d89_upscaled.png',
        'assets/fastival_images/parades.jpg',
      ],
      keyAttractionsEn: [
        'Traditional Bedouin Tents',
        'Camel Riding Experience',
        'Traditional Coffee Ceremony',
        'Desert Life Exhibition',
        'Bedouin Handicrafts Market',
      ],
      keyAttractionsAr: [
        'الخيام البدوية التقليدية',
        'تجربة ركوب الجمال',
        'مراسم القهوة التقليدية',
        'معرض الحياة الصحراوية',
        'سوق الحرف اليدوية البدوية',
      ],
      openingHours: '9:00 AM - 10:00 PM',
      craftDemonstrations: [
        CraftDemonstration(
          id: 'tent-making',
          titleEn: 'Traditional Tent Making',
          titleAr: 'صناعة الخيام التقليدية',
          descriptionEn: 'See how Bedouin tents are traditionally made from goat hair.',
          descriptionAr: 'شاهد كيف تُصنع الخيام البدوية تقليدياً من شعر الماعز.',
          craftspersonName: 'Khalifa Al-Wahaibi',
          imageUrl: 'assets/fastival_images/Yousuf__A_captivating_night-time_scene_featuring_an_open_0f73f5ee-9760-4235-b0f2-5ab2127d5762.png',
          scheduleTimes: ['10:00 AM', '3:00 PM', '7:00 PM'],
          durationMinutes: 50,
        ),
        CraftDemonstration(
          id: 'leather-working',
          titleEn: 'Traditional Leather Working',
          titleAr: 'صناعة الجلود التقليدية',
          descriptionEn: 'Watch craftsmen create traditional leather goods.',
          descriptionAr: 'شاهد الحرفيين وهم يصنعون المنتجات الجلدية التقليدية.',
          craftspersonName: 'Salem Al-Rashdi',
          imageUrl: 'assets/fastival_images/Unknown-scaled.jpeg',
          scheduleTimes: ['11:00 AM', '4:00 PM', '8:00 PM'],
          durationMinutes: 40,
        ),
      ],
      activities: [
        TraditionalActivity(
          id: 'camel-riding',
          nameEn: 'Camel Riding Experience',
          nameAr: 'تجربة ركوب الجمال',
          descriptionEn: 'Enjoy a traditional camel ride around the village.',
          descriptionAr: 'استمتع بركوب الجمال التقليدي حول القرية.',
          imageUrl: 'assets/fastival_images/parades.jpg',
          isInteractive: true,
          ageRecommendation: '5+',
          timing: 'Every 30 minutes',
        ),
        TraditionalActivity(
          id: 'coffee-ceremony',
          nameEn: 'Arabic Coffee Ceremony',
          nameAr: 'مراسم القهوة العربية',
          descriptionEn: 'Experience traditional Omani hospitality with coffee and dates.',
          descriptionAr: 'عش تجربة الضيافة العمانية التقليدية مع القهوة والتمر.',
          imageUrl: 'assets/fastival_images/Yousuf__A_magical_night-time_culinary_experience_set_in_a_64e8cfd2-0173-48e4-a36a-5b8056f25085_upscaled.png',
          isInteractive: true,
          ageRecommendation: 'All ages',
          timing: 'Every hour',
        ),
      ],
      workshops: [
        CulturalWorkshop(
          id: 'henna-art',
          titleEn: 'Traditional Henna Art',
          titleAr: 'فن الحناء التقليدي',
          descriptionEn: 'Learn the art of traditional henna design and application.',
          descriptionAr: 'تعلم فن تصميم وتطبيق الحناء التقليدي.',
          instructorName: 'Aisha Al-Hinai',
          imageUrl: 'assets/fastival_images/Art works.png',
          scheduleTimes: ['2:00 PM', '5:00 PM', '8:00 PM'],
          durationMinutes: 60,
          maxParticipants: 12,
          priceOMR: 8.0,
          skillLevel: 'Beginner',
        ),
        CulturalWorkshop(
          id: 'bedouin-cooking',
          titleEn: 'Traditional Bedouin Cooking',
          titleAr: 'الطبخ البدوي التقليدي',
          descriptionEn: 'Learn to prepare traditional Bedouin dishes.',
          descriptionAr: 'تعلم إعداد الأطباق البدوية التقليدية.',
          instructorName: 'Um Khalid',
          imageUrl: 'assets/fastival_images/Yousuf__A_magical_night-time_culinary_experience_set_in_a_d3856cb1-85d9-48a1-ae66-04acac20cf2f_upscaled.png',
          scheduleTimes: ['11:00 AM', '6:00 PM'],
          durationMinutes: 120,
          maxParticipants: 10,
          priceOMR: 12.0,
          skillLevel: 'All levels',
        ),
      ],
      photoSpots: [
        PhotoSpot(
          id: 'desert-camp',
          nameEn: 'Desert Camp at Twilight',
          nameAr: 'المخيم الصحراوي عند الشفق',
          descriptionEn: 'Capture the magic of a traditional Bedouin camp.',
          descriptionAr: 'التقط سحر المخيم البدوي التقليدي.',
          imageUrl: 'assets/fastival_images/Yousuf__A_realistic_night-time_scene_at_a_festival_where_a4adb264-ab78-429a-bb0f-25d9d8f65d89_upscaled.png',
          bestTimeForPhotos: 'Blue hour (6:30 PM - 7:00 PM)',
          photographyTips: [
            'Include the warm glow of campfires',
            'Capture silhouettes against the twilight sky',
            'Use traditional tents as foreground elements',
          ],
        ),
      ],
    ),
  ];

  static final List<CulturalTimelineEvent> timeline = [
    CulturalTimelineEvent(
      id: 'timeline-1',
      year: '3000 BCE',
      titleEn: 'Early Settlements in Sohar',
      titleAr: 'المستوطنات المبكرة في صحار',
      descriptionEn: 'Archaeological evidence shows early human settlements in the Sohar region.',
      descriptionAr: 'تظهر الأدلة الأثرية وجود مستوطنات بشرية مبكرة في منطقة صحار.',
      imageUrl: 'assets/fastival_images/Unknown-scaled.jpeg',
      category: 'Ancient History',
      isKeyMilestone: true,
    ),
    CulturalTimelineEvent(
      id: 'timeline-2',
      year: '1000 BCE',
      titleEn: 'Copper Trade Flourishes',
      titleAr: 'ازدهار تجارة النحاس',
      descriptionEn: 'Sohar becomes a major center for copper mining and trade.',
      descriptionAr: 'تصبح صحار مركزاً رئيسياً لتعدين وتجارة النحاس.',
      imageUrl: 'assets/fastival_images/1705044621-1705044621-dlnp1cqjz6ev.jpeg',
      category: 'Trade & Commerce',
      isKeyMilestone: true,
    ),
    CulturalTimelineEvent(
      id: 'timeline-3',
      year: '8th Century CE',
      titleEn: 'Islamic Golden Age',
      titleAr: 'العصر الذهبي الإسلامي',
      descriptionEn: 'Sohar emerges as one of the most important ports in the Islamic world.',
      descriptionAr: 'تبرز صحار كواحدة من أهم الموانئ في العالم الإسلامي.',
      imageUrl: 'assets/fastival_images/Screenshot-2024-08-15-080741.jpg',
      category: 'Islamic Era',
      isKeyMilestone: true,
    ),
    CulturalTimelineEvent(
      id: 'timeline-4',
      year: '10th Century',
      titleEn: 'Birthplace of Sinbad the Sailor',
      titleAr: 'مسقط رأس السندباد البحار',
      descriptionEn: 'Sohar gains fame as the legendary birthplace of Sinbad the Sailor.',
      descriptionAr: 'تشتهر صحار كمسقط رأس السندباد البحار الأسطوري.',
      imageUrl: 'assets/fastival_images/festival_image_oman_website_2.avif',
      category: 'Culture & Legend',
    ),
    CulturalTimelineEvent(
      id: 'timeline-5',
      year: '1507',
      titleEn: 'Portuguese Occupation',
      titleAr: 'الاحتلال البرتغالي',
      descriptionEn: 'Portuguese forces capture Sohar and build fortifications.',
      descriptionAr: 'تستولي القوات البرتغالية على صحار وتبني التحصينات.',
      imageUrl: 'assets/fastival_images/66e9818dbaaf4.jpeg',
      category: 'Colonial Period',
    ),
    CulturalTimelineEvent(
      id: 'timeline-6',
      year: '1650',
      titleEn: 'Liberation from Portuguese',
      titleAr: 'التحرر من البرتغاليين',
      descriptionEn: 'Omani forces successfully expel the Portuguese from Sohar.',
      descriptionAr: 'تنجح القوات العمانية في طرد البرتغاليين من صحار.',
      imageUrl: 'assets/fastival_images/Narak Platform 1.png',
      category: 'Independence',
      isKeyMilestone: true,
    ),
    CulturalTimelineEvent(
      id: 'timeline-7',
      year: '1970',
      titleEn: 'Modern Renaissance Begins',
      titleAr: 'بداية النهضة الحديثة',
      descriptionEn: 'Under Sultan Qaboos, Sohar begins its transformation into a modern city.',
      descriptionAr: 'تحت قيادة السلطان قابوس، تبدأ صحار تحولها إلى مدينة حديثة.',
      imageUrl: 'assets/fastival_images/Show.png',
      category: 'Modern Era',
      isKeyMilestone: true,
    ),
    CulturalTimelineEvent(
      id: 'timeline-8',
      year: '2004',
      titleEn: 'Sohar Port Development',
      titleAr: 'تطوير ميناء صحار',
      descriptionEn: 'Major development of Sohar Port begins, establishing it as a key logistics hub.',
      descriptionAr: 'يبدأ التطوير الرئيسي لميناء صحار، مما يجعله مركزاً لوجستياً رئيسياً.',
      imageUrl: 'assets/fastival_images/image.webp',
      category: 'Infrastructure',
    ),
    CulturalTimelineEvent(
      id: 'timeline-9',
      year: '2025',
      titleEn: 'Sohar Cultural Festival',
      titleAr: 'مهرجان صحار الثقافي',
      descriptionEn: 'Annual cultural festival celebrating Sohar\'s rich heritage and modern achievements.',
      descriptionAr: 'مهرجان ثقافي سنوي يحتفل بالتراث الغني لصحار وإنجازاتها الحديثة.',
      imageUrl: 'assets/fastival_images/Yousuf__A_breathtaking_night-time_human-perspective_scene_aaf6b3d2-34f1-4f3b-9653-27654b731acb_upscaled.png',
      category: 'Contemporary',
      isKeyMilestone: true,
    ),
  ];

  static List<HeritageVillage> getVillagesByType(VillageType type) {
    return villages.where((village) => village.type == type).toList();
  }

  static HeritageVillage? getVillageById(String id) {
    try {
      return villages.firstWhere((village) => village.id == id);
    } catch (e) {
      return null;
    }
  }

  static List<CulturalTimelineEvent> getTimelineByCategory(String category) {
    return timeline.where((event) => event.category == category).toList();
  }

  static List<CulturalTimelineEvent> getKeyMilestones() {
    return timeline.where((event) => event.isKeyMilestone).toList();
  }
}


import 'dart:math';

/// Service for managing festival images and mapping them to appropriate events
class FestivalImageService {
  // Private constructor to prevent instantiation
  FestivalImageService._();

  // Base path for festival images
  static const String _basePath = 'assets/fastival_images/';

  // Categorized festival images
  static const Map<String, List<String>> _imageCategories = {
    'heritage_cultural': [
      'Yousuf__A_bustling_sunset_scene_of_the_Sohar_Agricultural_30b63713-e228-4682-87a7-edbd807f67d7_upscaled.png',
      'Yousuf__A_night-time_human-perspective_scene_set_inside_a_01d2e2be-b40c-4f10-9fb0-9d48f8567339.png',
      'Yousuf__A_night-time_human-perspective_scene_set_inside_a_f42fb515-376c-4158-84f9-a02b12439d8b.png',
      'Unknown-scaled.jpeg',
      'festival_image_oman_website_2.avif',
    ],
    'entertainment_nighttime': [
      'Yousuf__A_breathtaking_night-time_human-perspective_scene_aaf6b3d2-34f1-4f3b-9653-27654b731acb_upscaled.png',
      'Yousuf__A_dramatic,_night-time_human-perspective_scene_of_0fa5cebd-44c7-4a61-8ebf-951e4aaa7f64_upscaled.png',
      'Yousuf__A_dynamic,_night-time_scene_at_the_Sohar_Festival_4fcebef5-2dd2-44a3-aac5-d871e4091f89_upscaled.png',
      'Yousuf__A_dynamic,_night-time_scene_at_the_Sohar_Festival_4fcebef5-2dd2-44a3-aac5-d871e4091f89_upscaledww.png',
      'Yousuf__A_human-perspective,_night-time_festival_scene_fe_3dfb4c9d-705b-4733-a1b4-e26ad88c587d_upscaled.png',
      'Yousuf__A_human-perspective,_night-time_festival_scene_fe_ee1582fc-541c-4c2c-ac4a-1d9f300a8a1b_upscaled.png',
      'Yousuf__A_hyper-realistic,_night-time_scene_at_Sohar_Fest_00dcc2b2-8ad9-42a3-8807-185f203b7a1e_upscaled.png',
    ],
    'food_culinary': [
      'Yousuf__A_magical_night-time_culinary_experience_set_in_a_64e8cfd2-0173-48e4-a36a-5b8056f25085_upscaled.png',
      'Yousuf__A_magical_night-time_culinary_experience_set_in_a_d3856cb1-85d9-48a1-ae66-04acac20cf2f_upscaled.png',
      'Yousuf__A_night-time_human-scale_outdoor_market_scene_tit_26ee8e3b-37a6-4eb7-93be-a4301fa153fc_upscaled.png',
    ],
    'arts_performance': [
      'Art works.png',
      'Show.png',
      'Yousuf__A_night-time_human-perspective_scene_of_a_dancing_e16a03ee-3293-4932-953f-36c91a1a3461.png',
      'Yousuf__A_realistic,_night-time_human-perspective_scene_o_68925e92-1c7c-4b24-82c1-f64cf1bc3a0a_upscaled.png',
      'Yousuf__A_realistic_night-time_scene_at_a_festival_where_a4adb264-ab78-429a-bb0f-25d9d8f65d89_upscaled.png',
      'Yousuf__A_vibrant_night-time_scene_inside_a_large,_enclos_144c0215-25a2-4770-aa77-e4a4ef3bbe50.png',
    ],
    'kids_family': [
      'Yousuf__A_zoomed-in,_human-perspective_night-time_scene_i_713e6dde-6a11-491d-8e36-d98e70589b8a.png',
      'parades.jpg',
    ],
    'special_venues': [
      'Narak Platform 1.png',
      'Yousuf__A_night-time_scene_inside_a_modern,_minimalist_pa_64029b1f-aa5c-4f33-84e9-220336d59a4d.png',
      'Yousuf____A_high-energy_night-time_scene_of_a_temporary_k_b9b3e58d-183d-4999-9853-3a0eb4dfc8ab.png',
      'Yousuf____A_human-perspective_night-time_scene_at_a_festi_c2c3d802-f777-40e4-8f4e-25c90121df2d.png',
    ],
    'general_festival': [
      '1705044621-1705044621-dlnp1cqjz6ev.jpeg',
      '66e9818dbaaf4.jpeg',
      'Screenshot-2024-08-15-080741.jpg',
      'IMG_5073.JPG',
      'download.avif',
      'download.png',
      'image.webp',
    ],
    'logos': [
      'AWJ LOGO.png',
      'bngLogoTrans (1).png',
    ],
  };

  // Event category to image category mapping
  static const Map<String, List<String>> _eventToImageMapping = {
    'cultural_heritage': ['heritage_cultural', 'general_festival'],
    'entertainment_visual': ['entertainment_nighttime', 'special_venues', 'arts_performance'],
    'arts_theater': ['arts_performance', 'special_venues'],
    'sports_activities': ['general_festival', 'special_venues'],
    'food_culinary': ['food_culinary', 'general_festival'],
    'workshops_learning': ['heritage_cultural', 'general_festival'],
    'kids_family': ['kids_family', 'general_festival'],
    'technology_innovation': ['special_venues', 'general_festival'],
    'tourism_economic': ['general_festival', 'special_venues'],
    'youth_innovation': ['special_venues', 'general_festival'],
  };

  // Time-based image preferences (for evening/night events)
  static const List<String> _nightTimeCategories = [
    'entertainment_nighttime',
    'arts_performance',
    'special_venues',
  ];

  /// Get an appropriate image for an event based on its category and time
  static String getEventImage(String eventCategory, DateTime? eventTime, {String? eventId}) {
    final imageCategories = _eventToImageMapping[eventCategory] ?? ['general_festival'];
    
    // Prefer night-time images for evening events (after 6 PM)
    bool isEveningEvent = eventTime != null && eventTime.hour >= 18;
    
    List<String> availableImages = [];
    
    if (isEveningEvent) {
      // First try to get night-time images
      for (String category in imageCategories) {
        if (_nightTimeCategories.contains(category)) {
          availableImages.addAll(_imageCategories[category] ?? []);
        }
      }
    }
    
    // If no night-time images found or not evening event, use any from the categories
    if (availableImages.isEmpty) {
      for (String category in imageCategories) {
        availableImages.addAll(_imageCategories[category] ?? []);
      }
    }
    
    // If still no images, fallback to general festival images
    if (availableImages.isEmpty) {
      availableImages = _imageCategories['general_festival'] ?? [];
    }
    
    // Use event ID for consistent image selection (same event always gets same image)
    if (eventId != null && availableImages.isNotEmpty) {
      final index = eventId.hashCode.abs() % availableImages.length;
      return '$_basePath${availableImages[index]}';
    }
    
    // Random fallback
    if (availableImages.isNotEmpty) {
      final random = Random();
      return _basePath + availableImages[random.nextInt(availableImages.length)];
    }
    
    // Ultimate fallback
    return '${_basePath}festival_image_oman_website_2.avif';
  }

  /// Get a specific image from a category
  static String getImageFromCategory(String category, {int index = 0}) {
    final images = _imageCategories[category];
    if (images == null || images.isEmpty) {
      return '${_basePath}festival_image_oman_website_2.avif';
    }
    
    final safeIndex = index % images.length;
    return '$_basePath${images[safeIndex]}';
  }

  /// Get featured event images (high-quality, diverse selection)
  static List<String> getFeaturedEventImages() {
    return [
      '${_basePath}Yousuf__A_breathtaking_night-time_human-perspective_scene_aaf6b3d2-34f1-4f3b-9653-27654b731acb_upscaled.png',
      '${_basePath}Yousuf__A_bustling_sunset_scene_of_the_Sohar_Agricultural_30b63713-e228-4682-87a7-edbd807f67d7_upscaled.png',
      '${_basePath}Yousuf__A_magical_night-time_culinary_experience_set_in_a_64e8cfd2-0173-48e4-a36a-5b8056f25085_upscaled.png',
      '${_basePath}Yousuf__A_dynamic,_night-time_scene_at_the_Sohar_Festival_4fcebef5-2dd2-44a3-aac5-d871e4091f89_upscaled.png',
    ];
  }

  /// Get restaurant images based on cuisine type
  static String getRestaurantImage(String cuisineType, {String? restaurantId}) {
    Map<String, List<String>> cuisineImageMapping = {
      'traditional': ['heritage_cultural', 'food_culinary'],
      'seafood': ['food_culinary', 'general_festival'],
      'international': ['food_culinary', 'general_festival'],
      'cafe': ['general_festival', 'food_culinary'],
    };

    String cuisineKey = cuisineType.toLowerCase().contains('omani') || cuisineType.toLowerCase().contains('traditional')
        ? 'traditional'
        : cuisineType.toLowerCase().contains('seafood')
            ? 'seafood'
            : cuisineType.toLowerCase().contains('cafe')
                ? 'cafe'
                : 'international';

    final imageCategories = cuisineImageMapping[cuisineKey] ?? ['food_culinary'];
    List<String> availableImages = [];
    
    for (String category in imageCategories) {
      availableImages.addAll(_imageCategories[category] ?? []);
    }
    
    if (availableImages.isEmpty) {
      availableImages = _imageCategories['general_festival'] ?? [];
    }
    
    // Use restaurant ID for consistent image selection
    if (restaurantId != null && availableImages.isNotEmpty) {
      final index = restaurantId.hashCode.abs() % availableImages.length;
      return '$_basePath${availableImages[index]}';
    }
    
    // Random fallback
    if (availableImages.isNotEmpty) {
      final random = Random();
      return _basePath + availableImages[random.nextInt(availableImages.length)];
    }
    
    return '${_basePath}festival_image_oman_website_2.avif';
  }

  /// Get all available image categories
  static List<String> getAllCategories() {
    return _imageCategories.keys.toList();
  }

  /// Get all images from a specific category
  static List<String> getImagesFromCategory(String category) {
    return _imageCategories[category]?.map((img) => '$_basePath$img').toList() ?? [];
  }

  /// Get a default fallback image
  static String getDefaultImage() {
    return '${_basePath}festival_image_oman_website_2.avif';
  }

  /// Get logo images
  static List<String> getLogos() {
    return _imageCategories['logos']?.map((img) => '$_basePath$img').toList() ?? [];
  }
}



import 'package:flutter/material.dart';
import '../models/event_model.dart';
import '../models/restaurant_model.dart';
import '../models/ticket_model.dart';
import '../constants/app_constants.dart';
import 'festival_image_service.dart';

enum EventStatus { upcoming, ongoing, past }

class MockDataService {
  static DateTime _getEventTime(int daysFromNow, String time) {
    final now = DateTime.now();
    final eventDay = now.add(Duration(days: daysFromNow));
    final timeParts = time.replaceAll(' AM', '').replaceAll(' PM', '').split(':');
    int hour = int.parse(timeParts[0]);
    final minute = int.parse(timeParts[1]);
    
    if (time.contains('PM') && hour != 12) {
      hour += 12;
    } else if (time.contains('AM') && hour == 12) {
      hour = 0;
    }
    
    return DateTime(eventDay.year, eventDay.month, eventDay.day, hour, minute);
  }

  static List<EventModelWithStatus> featuredEvents = [
    EventModelWithStatus(
      event: EventModel(
        id: '1',
        title: 'Cultural Heritage Village',
        titleAr: 'قرية التراث الثقافي',
        description: 'Experience the rich heritage of Oman with traditional crafts, music, and dance performances.',
        descriptionAr: 'اكتشف التراث العماني الغني مع الحرف التقليدية والموسيقى والعروض الشعبية.',
        category: EventCategories.cultural,
        imageUrl: FestivalImageService.getEventImage(EventCategories.cultural, _getEventTime(2, '10:00 AM'), eventId: '1'),
        startTime: _getEventTime(2, '10:00 AM'),
        endTime: _getEventTime(2, '10:00 PM'),
        location: 'Sohar Heritage Park',
        locationAr: 'حديقة التراث بصحار',
        price: 0,
        availableTickets: 500,
        totalTickets: 500,
        isFeatured: true,
        organizerName: 'Sohar Municipality',
        organizerNameAr: 'بلدية صحار',
        latitude: 24.3428,
        longitude: 56.7234,
      ),
      status: EventStatus.upcoming,
    ),
    EventModelWithStatus(
      event: EventModel(
        id: '2',
        title: 'Main Stage Shows',
        titleAr: 'عروض المسرح الرئيسي',
        description: 'Spectacular performances featuring local and international artists.',
        descriptionAr: 'عروض مذهلة بمشاركة فنانين محليين ودوليين.',
        category: EventCategories.entertainment,
        imageUrl: FestivalImageService.getEventImage(EventCategories.entertainment, _getEventTime(1, '8:00 PM'), eventId: '2'),
        startTime: _getEventTime(1, '8:00 PM'),
        endTime: _getEventTime(1, '11:00 PM'),
        location: 'Sohar Amphitheater',
        locationAr: 'مدرج صحار',
        price: 15,
        availableTickets: 250,
        totalTickets: 1000,
        isFeatured: true,
        organizerName: 'Festival Entertainment Committee',
        organizerNameAr: 'لجنة الترفيه بالمهرجان',
        latitude: 24.3450,
        longitude: 56.7250,
      ),
      status: EventStatus.upcoming,
    ),
    EventModelWithStatus(
      event: EventModel(
        id: '3',
        title: 'Children\'s Theater',
        titleAr: 'مسرح الأطفال',
        description: 'Interactive shows and performances designed for children and families.',
        descriptionAr: 'عروض تفاعلية مصممة للأطفال والعائلات.',
        category: EventCategories.kids,
        imageUrl: FestivalImageService.getEventImage(EventCategories.kids, _getEventTime(3, '4:00 PM'), eventId: '3'),
        startTime: _getEventTime(3, '4:00 PM'),
        endTime: _getEventTime(3, '6:00 PM'),
        location: 'Kids Zone Theater',
        locationAr: 'مسرح منطقة الأطفال',
        price: 5,
        availableTickets: 150,
        totalTickets: 200,
        isFeatured: true,
        organizerName: 'Family Entertainment',
        organizerNameAr: 'الترفيه العائلي',
        latitude: 24.3400,
        longitude: 56.7200,
      ),
      status: EventStatus.upcoming,
    ),
    EventModelWithStatus(
      event: EventModel(
        id: '4',
        title: 'Light Shows on Sohar Fort',
        titleAr: 'عروض الضوء على قلعة صحار',
        description: 'Stunning light projection shows telling the story of Sohar\'s history.',
        descriptionAr: 'عروض ضوئية مذهلة تحكي قصة تاريخ صحار.',
        category: EventCategories.entertainment,
        imageUrl: FestivalImageService.getEventImage(EventCategories.entertainment, _getEventTime(0, '7:30 PM'), eventId: '4'),
        startTime: _getEventTime(0, '7:30 PM'),
        endTime: _getEventTime(0, '8:30 PM'),
        location: 'Sohar Fort',
        locationAr: 'قلعة صحار',
        price: 10,
        availableTickets: 500,
        totalTickets: 1000,
        isFeatured: true,
        organizerName: 'Visual Arts Committee',
        organizerNameAr: 'لجنة الفنون البصرية',
        latitude: 24.3470,
        longitude: 56.7270,
      ),
      status: EventStatus.ongoing,
    ),
    EventModelWithStatus(
      event: EventModel(
        id: '5',
        title: 'Traditional Omani Cooking Workshop',
        titleAr: 'ورشة الطبخ العماني التقليدي',
        description: 'Learn to cook authentic Omani dishes with local chefs.',
        descriptionAr: 'تعلم طبخ الأطباق العمانية الأصيلة مع الطهاة المحليين.',
        category: EventCategories.food,
        imageUrl: FestivalImageService.getEventImage(EventCategories.food, _getEventTime(2, '2:00 PM'), eventId: '5'),
        startTime: _getEventTime(2, '2:00 PM'),
        endTime: _getEventTime(2, '5:00 PM'),
        location: 'Culinary Village',
        locationAr: 'القرية الطهوية',
        price: 25,
        availableTickets: 50,
        totalTickets: 50,
        isFeatured: true,
        organizerName: 'Sohar Culinary Academy',
        organizerNameAr: 'أكاديمية صحار للطهي',
        latitude: 24.3460,
        longitude: 56.7240,
      ),
      status: EventStatus.upcoming,
    ),
    EventModelWithStatus(
      event: EventModel(
        id: '6',
        title: 'Traditional Arts & Crafts Exhibition',
        titleAr: 'معرض الفنون والحرف التقليدية',
        description: 'Explore traditional Omani arts, crafts, and live demonstrations.',
        descriptionAr: 'استكشف الفنون والحرف العمانية التقليدية والعروض الحية.',
        category: EventCategories.arts,
        imageUrl: FestivalImageService.getEventImage(EventCategories.arts, _getEventTime(1, '10:00 AM'), eventId: '6'),
        startTime: _getEventTime(1, '10:00 AM'),
        endTime: _getEventTime(1, '8:00 PM'),
        location: 'Artisan Village',
        locationAr: 'قرية الحرفيين',
        price: 0,
        availableTickets: 300,
        totalTickets: 300,
        isFeatured: true,
        organizerName: 'Omani Heritage Foundation',
        organizerNameAr: 'مؤسسة التراث العماني',
        latitude: 24.3440,
        longitude: 56.7220,
      ),
      status: EventStatus.upcoming,
    ),
  ];

  static List<LiveUpdate> getLiveUpdates() {
    return [
      LiveUpdate(
        id: '1',
        title: 'Traditional Music Performance',
        titleAr: 'عرض موسيقي تقليدي',
        description: 'Starting in 30 minutes at Main Stage',
        descriptionAr: 'يبدأ بعد 30 دقيقة على المسرح الرئيسي',
        time: DateTime.now().add(const Duration(minutes: 30)),
        type: UpdateType.upcoming,
        icon: Icons.music_note,
      ),
      LiveUpdate(
        id: '2',
        title: 'Fireworks Show Tonight',
        titleAr: 'عرض الألعاب النارية الليلة',
        description: 'Don\'t miss the spectacular fireworks at 9 PM',
        descriptionAr: 'لا تفوت عرض الألعاب النارية المذهل في الساعة 9 مساءً',
        time: DateTime.now().add(const Duration(hours: 5)),
        type: UpdateType.announcement,
        icon: Icons.celebration,
      ),
      LiveUpdate(
        id: '3',
        title: 'Food Festival Open',
        titleAr: 'مهرجان الطعام مفتوح',
        description: 'Taste traditional Omani cuisine',
        descriptionAr: 'تذوق المأكولات العمانية التقليدية',
        time: DateTime.now(),
        type: UpdateType.ongoing,
        icon: Icons.restaurant,
      ),
    ];
  }

  static List<Announcement> getAnnouncements() {
    return [
      Announcement(
        id: '1',
        title: 'Festival Extended!',
        titleAr: 'تمديد المهرجان!',
        description: 'Due to popular demand, the festival has been extended by 2 days',
        descriptionAr: 'نظراً للإقبال الكبير، تم تمديد المهرجان ليومين إضافيين',
        priority: AnnouncementPriority.high,
        icon: Icons.announcement,
      ),
      Announcement(
        id: '2',
        title: 'Free Parking Available',
        titleAr: 'مواقف مجانية متاحة',
        description: 'Free parking at the North and South entrances',
        descriptionAr: 'مواقف مجانية عند المداخل الشمالية والجنوبية',
        priority: AnnouncementPriority.medium,
        icon: Icons.local_parking,
      ),
    ];
  }

  static WeatherData getWeatherData() {
    return WeatherData(
      temperature: 24,
      condition: WeatherCondition.sunny,
      humidity: 45,
      windSpeed: 12,
    );
  }

  static List<QuickAccess> getQuickAccessItems() {
    return [
      QuickAccess(
        id: 'events',
        title: 'Events',
        titleAr: 'الفعاليات',
        icon: Icons.event,
        color: const Color(0xFF1E88E5), // Bright Blue
        route: '/events',
      ),
      QuickAccess(
        id: 'map',
        title: 'Map',
        titleAr: 'الخريطة',
        icon: Icons.map,
        color: const Color(0xFF43A047), // Emerald Green
        route: '/map',
      ),
      QuickAccess(
        id: 'restaurants',
        title: 'Restaurants',
        titleAr: 'المطاعم',
        icon: Icons.restaurant,
        color: const Color(0xFFF4A521), // Warm Orange/Gold
        route: '/restaurants',
      ),
      QuickAccess(
        id: 'tickets',
        title: 'Tickets',
        titleAr: 'التذاكر',
        icon: Icons.confirmation_number,
        color: const Color(0xFF2B4C8C), // Deep Blue
        route: '/tickets',
      ),
      QuickAccess(
        id: 'healthcare',
        title: 'Healthcare',
        titleAr: 'الرعاية الصحية',
        icon: Icons.medical_services,
        color: const Color(0xFFE53935), // Bright Red
        route: '/healthcare',
      ),
      QuickAccess(
        id: 'activities',
        title: 'Activities',
        titleAr: 'الأنشطة',
        icon: Icons.sports_soccer,
        color: const Color(0xFFFFC107), // Bright Yellow
        route: '/categories',
      ),
      QuickAccess(
        id: 'heritage',
        title: 'Heritage',
        titleAr: 'التراث',
        icon: Icons.museum,
        color: const Color(0xFFE8D5B7), // Warm Beige
        route: '/heritage',
      ),
    ];
  }

  // Restaurant data
  static List<RestaurantModel> get restaurants => [
    RestaurantModel(
      id: 'rest1',
      name: 'Table from Sohar\'s Land',
      nameAr: 'مائدة من أرض صحار',
      description: 'Experience authentic Omani cuisine with a modern twist. Our special festival dining experience showcases the rich culinary heritage of Sohar.',
      descriptionAr: 'جرب المأكولات العمانية الأصيلة بلمسة عصرية. تجربة تناول الطعام الخاصة بالمهرجان تعرض التراث الغني لصحار.',
      cuisine: 'Traditional Omani',
      cuisineAr: 'عماني تقليدي',
      location: 'Festival Main Stage Area',
      locationAr: 'منطقة المسرح الرئيسي للمهرجان',
      latitude: 24.3467,
      longitude: 56.7444,
      rating: 4.8,
      totalRatings: 234,
      priceRange: '\$\$\$',
      imageUrl: FestivalImageService.getRestaurantImage('Traditional Omani', restaurantId: 'rest1'),
      images: [
        FestivalImageService.getImageFromCategory('food_culinary', index: 0),
        FestivalImageService.getImageFromCategory('heritage_cultural', index: 0),
        FestivalImageService.getImageFromCategory('general_festival', index: 0),
      ],
      phone: '+968 2684 5678',
      website: 'www.soharfestival.om/dining',
      openingHours: {
        'Monday': '12:00 PM - 11:00 PM',
        'Tuesday': '12:00 PM - 11:00 PM',
        'Wednesday': '12:00 PM - 11:00 PM',
        'Thursday': '12:00 PM - 12:00 AM',
        'Friday': '2:00 PM - 12:00 AM',
        'Saturday': '12:00 PM - 12:00 AM',
        'Sunday': '12:00 PM - 11:00 PM',
      },
      features: ['WiFi', 'Parking', 'Family Section', 'Outdoor Seating', 'Festival Special Menu'],
      featuresAr: ['واي فاي', 'موقف سيارات', 'قسم عائلي', 'جلسات خارجية', 'قائمة خاصة بالمهرجان'],
      isOpen: true,
      isFeatured: true,
      isActive: true,
    ),
    RestaurantModel(
      id: 'rest2',
      name: 'Bait Al Luban',
      nameAr: 'بيت اللبان',
      description: 'Traditional Omani restaurant serving authentic dishes from across the Sultanate.',
      descriptionAr: 'مطعم عماني تقليدي يقدم أطباق أصيلة من جميع أنحاء السلطنة.',
      cuisine: 'Traditional Omani',
      cuisineAr: 'عماني تقليدي',
      location: 'Heritage Village Area',
      locationAr: 'منطقة القرية التراثية',
      latitude: 24.3467,
      longitude: 56.7454,
      rating: 4.6,
      totalRatings: 156,
      priceRange: '\$\$',
      imageUrl: FestivalImageService.getRestaurantImage('Traditional Omani', restaurantId: 'rest2'),
      phone: '+968 2684 5679',
      openingHours: {
        'Monday': '11:00 AM - 10:00 PM',
        'Tuesday': '11:00 AM - 10:00 PM',
        'Wednesday': '11:00 AM - 10:00 PM',
        'Thursday': '11:00 AM - 11:00 PM',
        'Friday': '2:00 PM - 11:00 PM',
        'Saturday': '11:00 AM - 11:00 PM',
        'Sunday': '11:00 AM - 10:00 PM',
      },
      features: ['WiFi', 'Parking', 'Family Section', 'Traditional Seating'],
      featuresAr: ['واي فاي', 'موقف سيارات', 'قسم عائلي', 'جلسات تقليدية'],
      isOpen: true,
      isFeatured: false,
      isActive: true,
    ),
    RestaurantModel(
      id: 'rest3',
      name: 'Marina Seafood',
      nameAr: 'مأكولات المارينا البحرية',
      description: 'Fresh seafood daily from Sohar\'s fishing boats. Specializing in grilled fish and traditional seafood dishes.',
      descriptionAr: 'مأكولات بحرية طازجة يوميا من قوارب صيد صحار. متخصصون في السمك المشوي والأطباق البحرية التقليدية.',
      cuisine: 'Seafood',
      cuisineAr: 'مأكولات بحرية',
      location: 'Maritime Heritage Village',
      locationAr: 'القرية التراثية البحرية',
      latitude: 24.3470,
      longitude: 56.7460,
      rating: 4.7,
      totalRatings: 189,
      priceRange: '\$\$\$',
      imageUrl: FestivalImageService.getRestaurantImage('Seafood', restaurantId: 'rest3'),
      phone: '+968 2684 5680',
      features: ['Fresh Catch Daily', 'Outdoor Seating', 'Sea View', 'Parking'],
      featuresAr: ['صيد طازج يومي', 'جلسات خارجية', 'إطلالة بحرية', 'موقف سيارات'],
      isOpen: true,
      isFeatured: false,
      isActive: true,
    ),
    RestaurantModel(
      id: 'rest4',
      name: 'Spice Route',
      nameAr: 'طريق التوابل',
      description: 'International cuisine featuring dishes from India, Pakistan, and the Middle East.',
      descriptionAr: 'مأكولات عالمية تضم أطباق من الهند وباكستان والشرق الأوسط.',
      cuisine: 'International',
      cuisineAr: 'عالمي',
      location: 'Food Court Area',
      locationAr: 'منطقة ساحة الطعام',
      latitude: 24.3465,
      longitude: 56.7450,
      rating: 4.4,
      totalRatings: 98,
      priceRange: '\$',
      imageUrl: FestivalImageService.getRestaurantImage('International', restaurantId: 'rest4'),
      phone: '+968 2684 5681',
      features: ['Vegetarian Options', 'Halal', 'Quick Service', 'Takeaway'],
      featuresAr: ['خيارات نباتية', 'حلال', 'خدمة سريعة', 'طلبات خارجية'],
      isOpen: true,
      isFeatured: false,
      isActive: true,
    ),
    RestaurantModel(
      id: 'rest5',
      name: 'Karak & Chapati',
      nameAr: 'كرك وشباتي',
      description: 'Popular local cafe serving traditional karak tea, chapati, and light snacks.',
      descriptionAr: 'مقهى محلي شهير يقدم شاي الكرك التقليدي والشباتي والوجبات الخفيفة.',
      cuisine: 'Cafe',
      cuisineAr: 'مقهى',
      location: 'Near Main Entrance',
      locationAr: 'بالقرب من المدخل الرئيسي',
      latitude: 24.3468,
      longitude: 56.7445,
      rating: 4.5,
      totalRatings: 267,
      priceRange: '\$',
      imageUrl: FestivalImageService.getRestaurantImage('Cafe', restaurantId: 'rest5'),
      phone: '+968 2684 5682',
      features: ['Quick Service', '24/7', 'Takeaway', 'Local Favorite'],
      featuresAr: ['خدمة سريعة', '24/7', 'طلبات خارجية', 'المفضل المحلي'],
      isOpen: true,
      isFeatured: false,
      isActive: true,
    ),
    RestaurantModel(
      id: 'rest6',
      name: 'Bedouin Tent Restaurant',
      nameAr: 'مطعم الخيمة البدوية',
      description: 'Experience traditional Bedouin hospitality with authentic desert cuisine.',
      descriptionAr: 'جرب الضيافة البدوية التقليدية مع المأكولات الصحراوية الأصيلة.',
      cuisine: 'Traditional Omani',
      cuisineAr: 'عماني تقليدي',
      location: 'Bedouin Heritage Village',
      locationAr: 'القرية التراثية البدوية',
      latitude: 24.3472,
      longitude: 56.7448,
      rating: 4.7,
      totalRatings: 145,
      priceRange: '\$\$',
      imageUrl: FestivalImageService.getRestaurantImage('Traditional Omani', restaurantId: 'rest6'),
      phone: '+968 2684 5683',
      features: ['Traditional Setting', 'Live Cooking', 'Cultural Experience', 'Family Section'],
      featuresAr: ['جلسة تقليدية', 'طبخ حي', 'تجربة ثقافية', 'قسم عائلي'],
      isOpen: true,
      isFeatured: false,
      isActive: true,
    ),
  ];

  // Map location data
  static Map<String, dynamic> get festivalVenueData => {
    'venues': [
      {
        'id': 'main_stage',
        'type': 'stage',
        'name': 'Main Stage',
        'nameAr': 'المسرح الرئيسي',
        'description': 'Main performance stage for concerts and shows',
        'descriptionAr': 'المسرح الرئيسي للحفلات والعروض',
        'latitude': 24.3467,
        'longitude': 56.7444,
        'icon': 'stage',
      },
      {
        'id': 'maritime_village',
        'type': 'heritage',
        'name': 'Maritime Heritage Village',
        'nameAr': 'القرية التراثية البحرية',
        'description': 'Experience Sohar\'s rich maritime history',
        'descriptionAr': 'جرب تاريخ صحار البحري الغني',
        'latitude': 24.3470,
        'longitude': 56.7460,
        'icon': 'boat',
      },
      {
        'id': 'agricultural_village',
        'type': 'heritage',
        'name': 'Agricultural Heritage Village',
        'nameAr': 'القرية التراثية الزراعية',
        'description': 'Traditional farming and agricultural displays',
        'descriptionAr': 'عروض الزراعة التقليدية',
        'latitude': 24.3465,
        'longitude': 56.7455,
        'icon': 'agriculture',
      },
      {
        'id': 'bedouin_village',
        'type': 'heritage',
        'name': 'Bedouin Heritage Village',
        'nameAr': 'القرية التراثية البدوية',
        'description': 'Desert culture and traditional Bedouin life',
        'descriptionAr': 'ثقافة الصحراء والحياة البدوية التقليدية',
        'latitude': 24.3472,
        'longitude': 56.7448,
        'icon': 'tent',
      },
      {
        'id': 'food_court_1',
        'type': 'food',
        'name': 'Main Food Court',
        'nameAr': 'ساحة الطعام الرئيسية',
        'description': 'Variety of local and international cuisines',
        'descriptionAr': 'مجموعة متنوعة من المأكولات المحلية والعالمية',
        'latitude': 24.3465,
        'longitude': 56.7450,
        'icon': 'restaurant',
      },
      {
        'id': 'parking_north',
        'type': 'parking',
        'name': 'North Parking',
        'nameAr': 'موقف السيارات الشمالي',
        'description': 'Main parking area - North entrance',
        'descriptionAr': 'منطقة وقوف السيارات الرئيسية - المدخل الشمالي',
        'latitude': 24.3475,
        'longitude': 56.7440,
        'icon': 'parking',
      },
      {
        'id': 'parking_south',
        'type': 'parking',
        'name': 'South Parking',
        'nameAr': 'موقف السيارات الجنوبي',
        'description': 'Secondary parking area - South entrance',
        'descriptionAr': 'منطقة وقوف السيارات الثانوية - المدخل الجنوبي',
        'latitude': 24.3458,
        'longitude': 56.7465,
        'icon': 'parking',
      },
      {
        'id': 'first_aid_1',
        'type': 'medical',
        'name': 'First Aid Station 1',
        'nameAr': 'محطة الإسعافات الأولية 1',
        'description': 'Medical assistance and first aid',
        'descriptionAr': 'المساعدة الطبية والإسعافات الأولية',
        'latitude': 24.3468,
        'longitude': 56.7452,
        'icon': 'medical',
      },
      {
        'id': 'first_aid_2',
        'type': 'medical',
        'name': 'First Aid Station 2',
        'nameAr': 'محطة الإسعافات الأولية 2',
        'description': 'Medical assistance and first aid',
        'descriptionAr': 'المساعدة الطبية والإسعافات الأولية',
        'latitude': 24.3463,
        'longitude': 56.7458,
        'icon': 'medical',
      },
      {
        'id': 'prayer_room_men',
        'type': 'prayer',
        'name': 'Men\'s Prayer Room',
        'nameAr': 'مصلى الرجال',
        'description': 'Prayer facilities for men',
        'descriptionAr': 'مرافق الصلاة للرجال',
        'latitude': 24.3470,
        'longitude': 56.7445,
        'icon': 'mosque',
      },
      {
        'id': 'prayer_room_women',
        'type': 'prayer',
        'name': 'Women\'s Prayer Room',
        'nameAr': 'مصلى النساء',
        'description': 'Prayer facilities for women',
        'descriptionAr': 'مرافق الصلاة للنساء',
        'latitude': 24.3471,
        'longitude': 56.7446,
        'icon': 'mosque',
      },
      {
        'id': 'restroom_1',
        'type': 'restroom',
        'name': 'Restrooms Block A',
        'nameAr': 'دورات المياه A',
        'description': 'Public restroom facilities',
        'descriptionAr': 'مرافق دورات المياه العامة',
        'latitude': 24.3466,
        'longitude': 56.7447,
        'icon': 'restroom',
      },
      {
        'id': 'restroom_2',
        'type': 'restroom',
        'name': 'Restrooms Block B',
        'nameAr': 'دورات المياه B',
        'description': 'Public restroom facilities',
        'descriptionAr': 'مرافق دورات المياه العامة',
        'latitude': 24.3469,
        'longitude': 56.7456,
        'icon': 'restroom',
      },
      {
        'id': 'info_center',
        'type': 'info',
        'name': 'Information Center',
        'nameAr': 'مركز المعلومات',
        'description': 'Festival information and assistance',
        'descriptionAr': 'معلومات المهرجان والمساعدة',
        'latitude': 24.3468,
        'longitude': 56.7445,
        'icon': 'info',
      },
    ],
    'categories': [
      {'id': 'heritage', 'name': 'Heritage Villages', 'nameAr': 'القرى التراثية', 'icon': 'museum', 'color': 0xFFE8D5B7},
      {'id': 'stage', 'name': 'Stages', 'nameAr': 'المسارح', 'icon': 'theater', 'color': 0xFF1E88E5},
      {'id': 'food', 'name': 'Food Courts', 'nameAr': 'ساحات الطعام', 'icon': 'restaurant', 'color': 0xFFF4A521},
      {'id': 'parking', 'name': 'Parking', 'nameAr': 'مواقف السيارات', 'icon': 'parking', 'color': 0xFF1E88E5},
      {'id': 'medical', 'name': 'First Aid', 'nameAr': 'الإسعافات الأولية', 'icon': 'medical', 'color': 0xFFE53935},
      {'id': 'prayer', 'name': 'Prayer Rooms', 'nameAr': 'مصليات', 'icon': 'mosque', 'color': 0xFF43A047},
      {'id': 'restroom', 'name': 'Restrooms', 'nameAr': 'دورات المياه', 'icon': 'wc', 'color': 0xFFFFC107},
      {'id': 'info', 'name': 'Information', 'nameAr': 'معلومات', 'icon': 'info', 'color': 0xFF2B4C8C},
    ],
  };

  // Menu items for restaurants
  static Map<String, List<Map<String, dynamic>>> get restaurantMenus => {
    'rest1': [
      {
        'category': 'Appetizers',
        'categoryAr': 'المقبلات',
        'items': [
          {'name': 'Hummus with Khubz', 'nameAr': 'حمص مع خبز', 'price': 2.5, 'description': 'Traditional chickpea dip with fresh bread'},
          {'name': 'Sambusa', 'nameAr': 'سمبوسة', 'price': 3.0, 'description': 'Crispy pastries filled with spiced meat or vegetables'},
          {'name': 'Fattoush Salad', 'nameAr': 'سلطة فتوش', 'price': 4.0, 'description': 'Fresh mixed salad with crispy bread'},
        ]
      },
      {
        'category': 'Main Courses',
        'categoryAr': 'الأطباق الرئيسية',
        'items': [
          {'name': 'Shuwa', 'nameAr': 'شواء', 'price': 15.0, 'description': 'Slow-cooked marinated lamb, traditional Omani specialty'},
          {'name': 'Majboos', 'nameAr': 'مجبوس', 'price': 12.0, 'description': 'Spiced rice with tender chicken or lamb'},
          {'name': 'Mashuai', 'nameAr': 'مشوي', 'price': 18.0, 'description': 'Grilled kingfish served with lemon rice'},
        ]
      },
      {
        'category': 'Desserts',
        'categoryAr': 'الحلويات',
        'items': [
          {'name': 'Halwa', 'nameAr': 'حلوى', 'price': 4.0, 'description': 'Traditional Omani sweet with rose water and cardamom'},
          {'name': 'Luqaimat', 'nameAr': 'لقيمات', 'price': 3.5, 'description': 'Sweet dumplings drizzled with date syrup'},
        ]
      },
    ],
  };

  // Restaurant special offers
  static List<Map<String, dynamic>> get specialOffers => [
    {
      'restaurantId': 'rest1',
      'title': 'Festival Special Menu',
      'titleAr': 'قائمة خاصة بالمهرجان',
      'description': '20% off on traditional Omani feast for families',
      'descriptionAr': 'خصم 20٪ على الوليمة العمانية التقليدية للعائلات',
      'validUntil': DateTime.now().add(const Duration(days: 30)),
    },
    {
      'restaurantId': 'rest3',
      'title': 'Fresh Catch of the Day',
      'titleAr': 'صيد اليوم الطازج',
      'description': 'Daily special on locally caught seafood',
      'descriptionAr': 'عرض يومي خاص على المأكولات البحرية المحلية',
      'validUntil': DateTime.now().add(const Duration(days: 30)),
    },
  ];

  // Dietary restrictions
  static List<Map<String, dynamic>> get dietaryOptions => [
    {'id': 'halal', 'name': 'Halal', 'nameAr': 'حلال'},
    {'id': 'vegetarian', 'name': 'Vegetarian', 'nameAr': 'نباتي'},
    {'id': 'vegan', 'name': 'Vegan', 'nameAr': 'نباتي صرف'},
    {'id': 'gluten_free', 'name': 'Gluten Free', 'nameAr': 'خالي من الغلوتين'},
    {'id': 'dairy_free', 'name': 'Dairy Free', 'nameAr': 'خالي من الألبان'},
  ];

  // Ticket data
  static List<TicketModel> get userTickets => [
    // Active tickets
    TicketModel(
      id: 'ticket1',
      eventId: '2',
      eventTitle: 'Main Stage Shows',
      eventTitleAr: 'عروض المسرح الرئيسي',
      eventLocation: 'Sohar Amphitheater',
      eventLocationAr: 'مدرج صحار',
      eventDate: _getEventTime(1, '8:00 PM'),
      eventTime: '8:00 PM - 11:00 PM',
      type: TicketType.vip,
      status: TicketStatus.active,
      price: 25.0,
      holderName: 'John Doe',
      seatNumber: 'VIP-A12',
      qrCode: 'SOH-2025-MAIN-VIP-001',
      purchaseDate: DateTime.now().subtract(const Duration(days: 5)),
      eventImageUrl: FestivalImageService.getEventImage(EventCategories.entertainment, _getEventTime(1, '8:00 PM'), eventId: '2'),
    ),
    TicketModel(
      id: 'ticket2',
      eventId: '3',
      eventTitle: 'Children\'s Theater',
      eventTitleAr: 'مسرح الأطفال',
      eventLocation: 'Kids Zone Theater',
      eventLocationAr: 'مسرح منطقة الأطفال',
      eventDate: _getEventTime(3, '4:00 PM'),
      eventTime: '4:00 PM - 6:00 PM',
      type: TicketType.standard,
      status: TicketStatus.active,
      price: 5.0,
      holderName: 'John Doe',
      qrCode: 'SOH-2025-KIDS-STD-002',
      purchaseDate: DateTime.now().subtract(const Duration(days: 3)),
      eventImageUrl: FestivalImageService.getEventImage(EventCategories.kids, _getEventTime(3, '4:00 PM'), eventId: '3'),
    ),
    TicketModel(
      id: 'ticket3',
      eventId: '4',
      eventTitle: 'Light Shows on Sohar Fort',
      eventTitleAr: 'عروض الضوء على قلعة صحار',
      eventLocation: 'Sohar Fort',
      eventLocationAr: 'قلعة صحار',
      eventDate: _getEventTime(0, '7:30 PM'),
      eventTime: '7:30 PM - 8:30 PM',
      type: TicketType.premium,
      status: TicketStatus.active,
      price: 20.0,
      holderName: 'John Doe',
      seatNumber: 'PREM-B5',
      qrCode: 'SOH-2025-LIGHT-PREM-003',
      purchaseDate: DateTime.now().subtract(const Duration(days: 1)),
      eventImageUrl: FestivalImageService.getEventImage(EventCategories.entertainment, _getEventTime(0, '7:30 PM'), eventId: '4'),
    ),
    // Past tickets
    TicketModel(
      id: 'ticket4',
      eventId: '5',
      eventTitle: 'Opening Ceremony',
      eventTitleAr: 'حفل الافتتاح',
      eventLocation: 'Main Stage',
      eventLocationAr: 'المسرح الرئيسي',
      eventDate: DateTime.now().subtract(const Duration(days: 7)),
      eventTime: '6:00 PM - 9:00 PM',
      type: TicketType.earlyBird,
      status: TicketStatus.used,
      price: 30.0,
      holderName: 'John Doe',
      seatNumber: 'EB-C20',
      qrCode: 'SOH-2025-OPEN-EB-004',
      purchaseDate: DateTime.now().subtract(const Duration(days: 14)),
      eventImageUrl: FestivalImageService.getEventImage(EventCategories.entertainment, DateTime.now().subtract(const Duration(days: 7)), eventId: '5'),
    ),
    TicketModel(
      id: 'ticket5',
      eventId: '6',
      eventTitle: 'Traditional Music Night',
      eventTitleAr: 'ليلة الموسيقى التقليدية',
      eventLocation: 'Heritage Village',
      eventLocationAr: 'القرية التراثية',
      eventDate: DateTime.now().subtract(const Duration(days: 3)),
      eventTime: '7:00 PM - 10:00 PM',
      type: TicketType.standard,
      status: TicketStatus.expired,
      price: 10.0,
      holderName: 'John Doe',
      qrCode: 'SOH-2025-MUSIC-STD-005',
      purchaseDate: DateTime.now().subtract(const Duration(days: 10)),
      eventImageUrl: FestivalImageService.getEventImage(EventCategories.cultural, DateTime.now().subtract(const Duration(days: 3)), eventId: '6'),
    ),
  ];

  static List<TicketPricing> get ticketPricing => [
    TicketPricing(
      eventId: '1',
      prices: {
        TicketType.standard: 0.0, // Free event
        TicketType.vip: 0.0,
        TicketType.premium: 0.0,
        TicketType.earlyBird: 0.0,
      },
      availableQuantity: {
        TicketType.standard: 500,
        TicketType.vip: 0,
        TicketType.premium: 0,
        TicketType.earlyBird: 0,
      },
      benefits: {
        TicketType.standard: 'General admission to Cultural Heritage Village',
        TicketType.vip: '',
        TicketType.premium: '',
        TicketType.earlyBird: '',
      },
      benefitsAr: {
        TicketType.standard: 'دخول عام إلى قرية التراث الثقافي',
        TicketType.vip: '',
        TicketType.premium: '',
        TicketType.earlyBird: '',
      },
    ),
    TicketPricing(
      eventId: '2',
      prices: {
        TicketType.standard: 15.0,
        TicketType.vip: 25.0,
        TicketType.premium: 40.0,
        TicketType.earlyBird: 12.0,
      },
      availableQuantity: {
        TicketType.standard: 150,
        TicketType.vip: 50,
        TicketType.premium: 25,
        TicketType.earlyBird: 0, // Sold out
      },
      benefits: {
        TicketType.standard: 'General admission seating',
        TicketType.vip: 'Premium seating + Complimentary refreshments',
        TicketType.premium: 'Front row seats + Meet & greet + VIP lounge access',
        TicketType.earlyBird: 'General admission with 20% discount',
      },
      benefitsAr: {
        TicketType.standard: 'مقاعد الدخول العام',
        TicketType.vip: 'مقاعد مميزة + مرطبات مجانية',
        TicketType.premium: 'مقاعد الصف الأول + لقاء الفنانين + دخول صالة كبار الشخصيات',
        TicketType.earlyBird: 'دخول عام مع خصم 20٪',
      },
    ),
    TicketPricing(
      eventId: '3',
      prices: {
        TicketType.standard: 5.0,
        TicketType.vip: 10.0,
        TicketType.premium: 15.0,
        TicketType.earlyBird: 4.0,
      },
      availableQuantity: {
        TicketType.standard: 100,
        TicketType.vip: 30,
        TicketType.premium: 20,
        TicketType.earlyBird: 0,
      },
      benefits: {
        TicketType.standard: 'General admission for kids and parents',
        TicketType.vip: 'Priority seating + Activity booklet',
        TicketType.premium: 'Front seats + Meet characters + Gift bag',
        TicketType.earlyBird: 'General admission with discount',
      },
      benefitsAr: {
        TicketType.standard: 'دخول عام للأطفال والوالدين',
        TicketType.vip: 'مقاعد ذات أولوية + كتيب أنشطة',
        TicketType.premium: 'مقاعد أمامية + لقاء الشخصيات + حقيبة هدايا',
        TicketType.earlyBird: 'دخول عام مع خصم',
      },
    ),
    TicketPricing(
      eventId: '4',
      prices: {
        TicketType.standard: 10.0,
        TicketType.vip: 20.0,
        TicketType.premium: 30.0,
        TicketType.earlyBird: 8.0,
      },
      availableQuantity: {
        TicketType.standard: 300,
        TicketType.vip: 100,
        TicketType.premium: 50,
        TicketType.earlyBird: 50,
      },
      benefits: {
        TicketType.standard: 'Standing area access',
        TicketType.vip: 'Reserved seating with best views',
        TicketType.premium: 'VIP terrace + Complimentary drinks + Souvenir',
        TicketType.earlyBird: 'Standing area with 20% discount',
      },
      benefitsAr: {
        TicketType.standard: 'دخول منطقة الوقوف',
        TicketType.vip: 'مقاعد محجوزة مع أفضل المناظر',
        TicketType.premium: 'شرفة كبار الشخصيات + مشروبات مجانية + تذكار',
        TicketType.earlyBird: 'منطقة الوقوف مع خصم 20٪',
      },
    ),
  ];

  static List<EventModel> get ticketableEvents => featuredEvents
      .where((event) => event.event.price > 0 || event.event.price == 0)
      .map((e) => e.event)
      .toList();

  // Healthcare data
  static HealthcareData getHealthcareData() {
    return HealthcareData(
      emergencyContacts: [
        EmergencyContact(
          name: 'Emergency Services',
          nameAr: 'خدمات الطوارئ',
          number: '999',
          description: 'Police, Fire, Ambulance',
          descriptionAr: 'الشرطة، الإطفاء، الإسعاف',
          priority: 'high',
          icon: Icons.emergency,
        ),
        EmergencyContact(
          name: 'Festival Medical Hotline',
          nameAr: 'الخط الساخن الطبي للمهرجان',
          number: '+968 2684 5999',
          description: '24/7 Medical assistance',
          descriptionAr: 'المساعدة الطبية على مدار الساعة',
          priority: 'high',
          icon: Icons.medical_services,
        ),
        EmergencyContact(
          name: 'Festival Security',
          nameAr: 'أمن المهرجان',
          number: '+968 2684 5111',
          description: 'Security and safety concerns',
          descriptionAr: 'الأمن والسلامة',
          priority: 'medium',
          icon: Icons.security,
        ),
        EmergencyContact(
          name: 'Lost Children Center',
          nameAr: 'مركز الأطفال المفقودين',
          number: '+968 2684 5222',
          description: 'Help finding lost children',
          descriptionAr: 'المساعدة في العثور على الأطفال المفقودين',
          priority: 'medium',
          icon: Icons.child_care,
        ),
      ],
      medicalServices: [
        MedicalService(
          title: 'Basic First Aid',
          titleAr: 'الإسعافات الأولية الأساسية',
          description: 'Minor injuries, cuts, and bruises treatment',
          descriptionAr: 'علاج الإصابات الطفيفة والجروح والكدمات',
          icon: Icons.healing,
          color: Colors.blue,
          details: [
            'Wound cleaning and bandaging',
            'Minor burn treatment',
            'Insect bite relief',
            'Pain relief medication',
          ],
          detailsAr: [
            'تنظيف الجروح والضمادات',
            'علاج الحروق الطفيفة',
            'علاج لدغات الحشرات',
            'أدوية تخفيف الألم',
          ],
          operatingHours: {
            'Daily': '10:00 AM - 11:00 PM',
            'يومياً': '10:00 ص - 11:00 م',
          },
        ),
        MedicalService(
          title: 'Emergency Medical Care',
          titleAr: 'الرعاية الطبية الطارئة',
          description: 'Serious medical emergencies and ambulance service',
          descriptionAr: 'حالات الطوارئ الطبية الخطيرة وخدمة الإسعاف',
          icon: Icons.emergency,
          color: Colors.red,
          details: [
            'Advanced life support',
            'Emergency stabilization',
            'Hospital transfer coordination',
            'Critical care paramedics',
          ],
          detailsAr: [
            'دعم الحياة المتقدم',
            'الاستقرار في حالات الطوارئ',
            'تنسيق النقل للمستشفى',
            'المسعفون للرعاية الحرجة',
          ],
          operatingHours: {
            '24/7': 'Available',
            '24/7_ar': 'متاح',
          },
        ),
        MedicalService(
          title: 'Pharmacy Services',
          titleAr: 'خدمات الصيدلية',
          description: 'Basic medications and health supplies',
          descriptionAr: 'الأدوية الأساسية واللوازم الصحية',
          icon: Icons.local_pharmacy,
          color: Colors.green,
          details: [
            'Over-the-counter medications',
            'Sunscreen and hydration salts',
            'Basic medical supplies',
            'Prescription verification',
          ],
          detailsAr: [
            'الأدوية بدون وصفة طبية',
            'واقي الشمس وأملاح الترطيب',
            'اللوازم الطبية الأساسية',
            'التحقق من الوصفات الطبية',
          ],
          operatingHours: {
            'Daily': '10:00 AM - 10:00 PM',
            'يومياً': '10:00 ص - 10:00 م',
          },
        ),
        MedicalService(
          title: 'Accessibility Services',
          titleAr: 'خدمات ذوي الاحتياجات الخاصة',
          description: 'Support for visitors with special needs',
          descriptionAr: 'دعم الزوار ذوي الاحتياجات الخاصة',
          icon: Icons.accessible,
          color: Colors.purple,
          details: [
            'Wheelchair rental',
            'Mobility assistance',
            'Sign language interpreters',
            'Accessible route guidance',
          ],
          detailsAr: [
            'تأجير الكراسي المتحركة',
            'المساعدة في التنقل',
            'مترجمو لغة الإشارة',
            'إرشاد المسارات المخصصة',
          ],
          operatingHours: {
            'Daily': '10:00 AM - 11:00 PM',
            'يومياً': '10:00 ص - 11:00 م',
          },
        ),
      ],
      prayerRooms: [
        PrayerRoom(
          name: 'Main Prayer Hall - Men',
          nameAr: 'قاعة الصلاة الرئيسية - رجال',
          location: 'Near Main Entrance',
          locationAr: 'بالقرب من المدخل الرئيسي',
          capacity: '200 people',
          capacityAr: '200 شخص',
          facilities: ['Ablution area', 'Air conditioning', 'Prayer mats'],
          facilitiesAr: ['منطقة الوضوء', 'تكييف', 'سجاد للصلاة'],
          prayerTimes: {
            'Fajr': '5:15 AM',
            'Dhuhr': '12:30 PM',
            'Asr': '3:45 PM',
            'Maghrib': '6:30 PM',
            'Isha': '8:00 PM',
          },
        ),
        PrayerRoom(
          name: 'Main Prayer Hall - Women',
          nameAr: 'قاعة الصلاة الرئيسية - نساء',
          location: 'Near Main Entrance',
          locationAr: 'بالقرب من المدخل الرئيسي',
          capacity: '150 people',
          capacityAr: '150 شخص',
          facilities: ['Ablution area', 'Air conditioning', 'Prayer mats', 'Private entrance'],
          facilitiesAr: ['منطقة الوضوء', 'تكييف', 'سجاد للصلاة', 'مدخل خاص'],
          prayerTimes: {
            'Fajr': '5:15 AM',
            'Dhuhr': '12:30 PM',
            'Asr': '3:45 PM',
            'Maghrib': '6:30 PM',
            'Isha': '8:00 PM',
          },
        ),
        PrayerRoom(
          name: 'Secondary Prayer Area',
          nameAr: 'منطقة الصلاة الثانوية',
          location: 'Food Court Area',
          locationAr: 'منطقة ساحة الطعام',
          capacity: '100 people',
          capacityAr: '100 شخص',
          facilities: ['Ablution area', 'Covered area'],
          facilitiesAr: ['منطقة الوضوء', 'منطقة مغطاة'],
          prayerTimes: {
            'Open': 'All prayer times',
            'مفتوح': 'جميع أوقات الصلاة',
          },
        ),
      ],
      familyFacilities: [
        FamilyFacility(
          name: 'Nursing & Baby Care Room',
          nameAr: 'غرفة الرضاعة والعناية بالأطفال',
          description: 'Private space for nursing mothers',
          descriptionAr: 'مساحة خاصة للأمهات المرضعات',
          icon: Icons.baby_changing_station,
          color: Colors.pink,
          features: [
            'Comfortable seating',
            'Baby changing stations',
            'Bottle warming facilities',
            'Privacy screens',
          ],
          featuresAr: [
            'مقاعد مريحة',
            'محطات تغيير الحفاضات',
            'مرافق تدفئة الرضّاعات',
            'حواجز الخصوصية',
          ],
          location: 'Near Main Stage & Food Court',
          locationAr: 'بالقرب من المسرح الرئيسي وساحة الطعام',
        ),
        FamilyFacility(
          name: 'Kids Rest Area',
          nameAr: 'منطقة راحة الأطفال',
          description: 'Safe space for children to rest',
          descriptionAr: 'مساحة آمنة لراحة الأطفال',
          icon: Icons.child_friendly,
          color: Colors.orange,
          features: [
            'Air-conditioned space',
            'Play mats',
            'Parent supervision area',
            'First aid available',
          ],
          featuresAr: [
            'مساحة مكيفة',
            'سجاد للعب',
            'منطقة إشراف الوالدين',
            'الإسعافات الأولية متاحة',
          ],
          location: 'Children\'s Activity Zone',
          locationAr: 'منطقة أنشطة الأطفال',
        ),
        FamilyFacility(
          name: 'Family Restrooms',
          nameAr: 'دورات مياه عائلية',
          description: 'Spacious restrooms for families',
          descriptionAr: 'دورات مياه واسعة للعائلات',
          icon: Icons.family_restroom,
          color: Colors.blue,
          features: [
            'Baby changing tables',
            'Child-sized facilities',
            'Wheelchair accessible',
            'Family assistance button',
          ],
          featuresAr: [
            'طاولات تغيير الحفاضات',
            'مرافق بحجم الأطفال',
            'مناسب للكراسي المتحركة',
            'زر المساعدة العائلية',
          ],
          location: 'Multiple locations',
          locationAr: 'مواقع متعددة',
        ),
      ],
      lostAndFoundInfo: [
        'Main Lost & Found Center near Information Desk',
        'Report lost items immediately',
        'Take photo of your belongings',
        'Keep valuable items secure',
        'Children should wear ID wristbands',
      ],
      lostAndFoundInfoAr: [
        'مركز المفقودات الرئيسي بالقرب من مكتب المعلومات',
        'أبلغ عن الأغراض المفقودة فوراً',
        'التقط صورة لممتلكاتك',
        'احتفظ بالأشياء الثمينة بأمان',
        'يجب على الأطفال ارتداء أساور التعريف',
      ],
      healthTips: [
        HealthTip(
          title: 'Stay Hydrated',
          titleAr: 'حافظ على الترطيب',
          description: 'Drink plenty of water throughout the day',
          descriptionAr: 'اشرب الكثير من الماء طوال اليوم',
          tips: [
            'Carry a refillable water bottle',
            'Drink water every 30 minutes',
            'Avoid excessive caffeine',
            'Watch for signs of dehydration',
          ],
          tipsAr: [
            'احمل زجاجة ماء قابلة لإعادة التعبئة',
            'اشرب الماء كل 30 دقيقة',
            'تجنب الإفراط في الكافيين',
            'راقب علامات الجفاف',
          ],
          icon: Icons.water_drop,
          color: Colors.blue,
          importance: 'high',
        ),
        HealthTip(
          title: 'Sun Protection',
          titleAr: 'الحماية من الشمس',
          description: 'Protect yourself from sun exposure',
          descriptionAr: 'احم نفسك من التعرض للشمس',
          tips: [
            'Apply sunscreen SPF 30+',
            'Wear a hat and sunglasses',
            'Seek shade during peak hours',
            'Reapply sunscreen every 2 hours',
          ],
          tipsAr: [
            'ضع واقي شمس بعامل حماية 30+',
            'ارتد قبعة ونظارات شمسية',
            'ابحث عن الظل خلال ساعات الذروة',
            'أعد وضع واقي الشمس كل ساعتين',
          ],
          icon: Icons.wb_sunny,
          color: Colors.orange,
          importance: 'high',
        ),
        HealthTip(
          title: 'Food Safety',
          titleAr: 'سلامة الغذاء',
          description: 'Eat safely at the festival',
          descriptionAr: 'تناول الطعام بأمان في المهرجان',
          tips: [
            'Choose freshly prepared food',
            'Check vendor hygiene ratings',
            'Wash hands before eating',
            'Avoid food if you have allergies',
          ],
          tipsAr: [
            'اختر الطعام المحضر حديثاً',
            'تحقق من تقييمات نظافة البائعين',
            'اغسل يديك قبل الأكل',
            'تجنب الطعام إذا كان لديك حساسية',
          ],
          icon: Icons.restaurant,
          color: Colors.green,
          importance: 'medium',
        ),
        HealthTip(
          title: 'Crowd Safety',
          titleAr: 'السلامة في الزحام',
          description: 'Stay safe in crowded areas',
          descriptionAr: 'ابق آمناً في المناطق المزدحمة',
          tips: [
            'Keep children close',
            'Establish meeting points',
            'Avoid pushing in crowds',
            'Exit if feeling overwhelmed',
          ],
          tipsAr: [
            'أبق الأطفال بالقرب منك',
            'حدد نقاط التقاء',
            'تجنب الدفع في الزحام',
            'اخرج إذا شعرت بالإرهاق',
          ],
          icon: Icons.groups,
          color: Colors.purple,
          importance: 'high',
        ),
        HealthTip(
          title: 'Personal Hygiene',
          titleAr: 'النظافة الشخصية',
          description: 'Maintain good hygiene practices',
          descriptionAr: 'حافظ على ممارسات النظافة الجيدة',
          tips: [
            'Use hand sanitizer regularly',
            'Cover coughs and sneezes',
            'Avoid touching your face',
            'Use tissues and dispose properly',
          ],
          tipsAr: [
            'استخدم معقم اليدين بانتظام',
            'غط السعال والعطس',
            'تجنب لمس وجهك',
            'استخدم المناديل وتخلص منها بشكل صحيح',
          ],
          icon: Icons.clean_hands,
          color: Colors.teal,
          importance: 'medium',
        ),
      ],
    );
  }

  // First aid stations
  static List<FirstAidStation> getFirstAidStations() {
    return [
      FirstAidStation(
        id: 'fa1',
        name: 'Main First Aid Center',
        nameAr: 'مركز الإسعافات الأولية الرئيسي',
        type: 'main',
        location: 'Near Main Stage',
        locationAr: 'بالقرب من المسرح الرئيسي',
        latitude: 24.3468,
        longitude: 56.7452,
        services: [
          'Emergency care',
          'Minor injuries',
          'Medications',
          'Ambulance standby',
        ],
        servicesAr: [
          'الرعاية الطارئة',
          'الإصابات الطفيفة',
          'الأدوية',
          'الإسعاف في الانتظار',
        ],
        staffCount: 8,
        contactNumber: '+968 2684 5999',
        isOpen: true,
      ),
      FirstAidStation(
        id: 'fa2',
        name: 'North Entrance Medical Point',
        nameAr: 'النقطة الطبية للمدخل الشمالي',
        type: 'main',
        location: 'North Entrance Gate',
        locationAr: 'بوابة المدخل الشمالي',
        latitude: 24.3475,
        longitude: 56.7440,
        services: [
          'First aid',
          'Heat exhaustion treatment',
          'Basic medications',
        ],
        servicesAr: [
          'الإسعافات الأولية',
          'علاج الإنهاك الحراري',
          'الأدوية الأساسية',
        ],
        staffCount: 4,
        contactNumber: '+968 2684 6001',
        isOpen: true,
      ),
      FirstAidStation(
        id: 'fa3',
        name: 'Mobile Unit Alpha',
        nameAr: 'الوحدة المتنقلة ألفا',
        type: 'mobile',
        location: 'Roaming - Food Court Area',
        locationAr: 'متجول - منطقة ساحة الطعام',
        latitude: 24.3465,
        longitude: 56.7450,
        services: [
          'Quick response',
          'Basic first aid',
          'Emergency referral',
        ],
        servicesAr: [
          'الاستجابة السريعة',
          'الإسعافات الأولية الأساسية',
          'الإحالة الطارئة',
        ],
        staffCount: 2,
        isOpen: true,
      ),
      FirstAidStation(
        id: 'fa4',
        name: 'Children\'s Area Medical Post',
        nameAr: 'المركز الطبي لمنطقة الأطفال',
        type: 'main',
        location: 'Kids Zone',
        locationAr: 'منطقة الأطفال',
        latitude: 24.3400,
        longitude: 56.7200,
        services: [
          'Pediatric care',
          'Minor injuries',
          'Allergy treatment',
          'Parent support',
        ],
        servicesAr: [
          'رعاية الأطفال',
          'الإصابات الطفيفة',
          'علاج الحساسية',
          'دعم الوالدين',
        ],
        staffCount: 3,
        contactNumber: '+968 2684 6002',
        isOpen: true,
      ),
      FirstAidStation(
        id: 'fa5',
        name: 'Emergency Response Point',
        nameAr: 'نقطة الاستجابة الطارئة',
        type: 'emergency',
        location: 'Central Plaza',
        locationAr: 'الساحة المركزية',
        latitude: 24.3467,
        longitude: 56.7444,
        services: [
          'Rapid response',
          'Critical care',
          'Ambulance coordination',
        ],
        servicesAr: [
          'الاستجابة السريعة',
          'الرعاية الحرجة',
          'تنسيق سيارات الإسعاف',
        ],
        staffCount: 6,
        contactNumber: '999',
        isOpen: true,
      ),
    ];
  }

  // Emergency information
  static EmergencyInfo getEmergencyInfo() {
    return EmergencyInfo(
      procedures: [
        EmergencyProcedure(
          title: 'Medical Emergency',
          titleAr: 'حالة طبية طارئة',
          description: 'What to do in case of medical emergency',
          descriptionAr: 'ما يجب فعله في حالة الطوارئ الطبية',
          icon: Icons.medical_services,
          color: Colors.red,
          steps: [
            'Call 999 or festival medical hotline',
            'Stay with the person in need',
            'Provide clear location details',
            'Follow dispatcher instructions',
            'Wait for medical team arrival',
          ],
          stepsAr: [
            'اتصل بـ 999 أو الخط الساخن الطبي للمهرجان',
            'ابق مع الشخص المحتاج',
            'قدم تفاصيل الموقع بوضوح',
            'اتبع تعليمات المرسل',
            'انتظر وصول الفريق الطبي',
          ],
        ),
        EmergencyProcedure(
          title: 'Fire Emergency',
          titleAr: 'حالة طوارئ حريق',
          description: 'Fire safety and evacuation procedures',
          descriptionAr: 'إجراءات السلامة من الحرائق والإخلاء',
          icon: Icons.local_fire_department,
          color: Colors.orange,
          steps: [
            'Alert others nearby',
            'Call 999 immediately',
            'Evacuate via nearest exit',
            'Do not use elevators',
            'Gather at designated assembly points',
          ],
          stepsAr: [
            'نبه الآخرين القريبين',
            'اتصل بـ 999 فوراً',
            'أخلِ عبر أقرب مخرج',
            'لا تستخدم المصاعد',
            'اجتمع في نقاط التجمع المحددة',
          ],
        ),
        EmergencyProcedure(
          title: 'Lost Child',
          titleAr: 'طفل مفقود',
          description: 'Steps to take if a child is lost',
          descriptionAr: 'الخطوات الواجب اتخاذها إذا فُقد طفل',
          icon: Icons.child_care,
          color: Colors.blue,
          steps: [
            'Stay calm and note last seen location',
            'Contact festival security immediately',
            'Provide child description and photo',
            'Check Lost Children Center',
            'One parent stay at last location',
          ],
          stepsAr: [
            'ابق هادئاً ولاحظ آخر موقع شوهد فيه',
            'اتصل بأمن المهرجان فوراً',
            'قدم وصف الطفل وصورته',
            'تحقق من مركز الأطفال المفقودين',
            'يبقى أحد الوالدين في آخر موقع',
          ],
        ),
        EmergencyProcedure(
          title: 'Security Threat',
          titleAr: 'تهديد أمني',
          description: 'Response to security concerns',
          descriptionAr: 'الاستجابة للمخاوف الأمنية',
          icon: Icons.security,
          color: Colors.purple,
          steps: [
            'Move to safe location',
            'Call security or 999',
            'Follow official instructions',
            'Avoid spreading rumors',
            'Help others if safe to do so',
          ],
          stepsAr: [
            'انتقل إلى موقع آمن',
            'اتصل بالأمن أو 999',
            'اتبع التعليمات الرسمية',
            'تجنب نشر الشائعات',
            'ساعد الآخرين إذا كان ذلك آمناً',
          ],
        ),
      ],
      evacuationPoints: [
        EvacuationPoint(
          name: 'North Assembly Point',
          nameAr: 'نقطة التجمع الشمالية',
          location: 'North Parking Area',
          locationAr: 'منطقة وقوف السيارات الشمالية',
          latitude: 24.3480,
          longitude: 56.7435,
        ),
        EvacuationPoint(
          name: 'South Assembly Point',
          nameAr: 'نقطة التجمع الجنوبية',
          location: 'South Parking Area',
          locationAr: 'منطقة وقوف السيارات الجنوبية',
          latitude: 24.3455,
          longitude: 56.7470,
        ),
        EvacuationPoint(
          name: 'East Assembly Point',
          nameAr: 'نقطة التجمع الشرقية',
          location: 'East Gate Open Area',
          locationAr: 'المنطقة المفتوحة للبوابة الشرقية',
          latitude: 24.3467,
          longitude: 56.7480,
        ),
      ],
      contacts: [
        EmergencyContact(
          name: 'Emergency Services',
          nameAr: 'خدمات الطوارئ',
          number: '999',
          description: 'Police, Fire, Ambulance',
          descriptionAr: 'الشرطة، الإطفاء، الإسعاف',
          priority: 'high',
          icon: Icons.emergency,
        ),
        EmergencyContact(
          name: 'Festival Medical',
          nameAr: 'الطبي للمهرجان',
          number: '+968 2684 5999',
          priority: 'high',
          icon: Icons.medical_services,
        ),
        EmergencyContact(
          name: 'Festival Security',
          nameAr: 'أمن المهرجان',
          number: '+968 2684 5111',
          priority: 'medium',
          icon: Icons.security,
        ),
        EmergencyContact(
          name: 'Information Desk',
          nameAr: 'مكتب المعلومات',
          number: '+968 2684 5000',
          priority: 'low',
          icon: Icons.info,
        ),
      ],
      safetyTips: [
        SafetyTip(
          tip: 'Keep emergency numbers saved in your phone',
          tipAr: 'احتفظ بأرقام الطوارئ محفوظة في هاتفك',
        ),
        SafetyTip(
          tip: 'Know the location of nearest exits',
          tipAr: 'اعرف موقع أقرب المخارج',
        ),
        SafetyTip(
          tip: 'Stay aware of your surroundings',
          tipAr: 'ابق على دراية بمحيطك',
        ),
        SafetyTip(
          tip: 'Keep identification on you at all times',
          tipAr: 'احتفظ بالهوية معك في جميع الأوقات',
        ),
        SafetyTip(
          tip: 'Establish family meeting points',
          tipAr: 'حدد نقاط لقاء العائلة',
        ),
      ],
    );
  }
}

// Wrapper class to add status to EventModel
class EventModelWithStatus {
  final EventModel event;
  final EventStatus status;

  EventModelWithStatus({
    required this.event,
    required this.status,
  });
}

// Supporting models for mock data
class LiveUpdate {
  final String id;
  final String title;
  final String titleAr;
  final String description;
  final String descriptionAr;
  final DateTime time;
  final UpdateType type;
  final IconData icon;

  LiveUpdate({
    required this.id,
    required this.title,
    required this.titleAr,
    required this.description,
    required this.descriptionAr,
    required this.time,
    required this.type,
    required this.icon,
  });
}

enum UpdateType { upcoming, ongoing, announcement }

class Announcement {
  final String id;
  final String title;
  final String titleAr;
  final String description;
  final String descriptionAr;
  final AnnouncementPriority priority;
  final IconData icon;

  Announcement({
    required this.id,
    required this.title,
    required this.titleAr,
    required this.description,
    required this.descriptionAr,
    required this.priority,
    required this.icon,
  });
}

enum AnnouncementPriority { high, medium, low }

class WeatherData {
  final int temperature;
  final WeatherCondition condition;
  final int humidity;
  final int windSpeed;

  WeatherData({
    required this.temperature,
    required this.condition,
    required this.humidity,
    required this.windSpeed,
  });

  String get conditionText {
    switch (condition) {
      case WeatherCondition.sunny:
        return 'Sunny';
      case WeatherCondition.cloudy:
        return 'Cloudy';
      case WeatherCondition.rainy:
        return 'Rainy';
      case WeatherCondition.windy:
        return 'Windy';
    }
  }

  String get conditionTextAr {
    switch (condition) {
      case WeatherCondition.sunny:
        return 'مشمس';
      case WeatherCondition.cloudy:
        return 'غائم';
      case WeatherCondition.rainy:
        return 'ممطر';
      case WeatherCondition.windy:
        return 'عاصف';
    }
  }

  IconData get icon {
    switch (condition) {
      case WeatherCondition.sunny:
        return Icons.wb_sunny;
      case WeatherCondition.cloudy:
        return Icons.cloud;
      case WeatherCondition.rainy:
        return Icons.umbrella;
      case WeatherCondition.windy:
        return Icons.air;
    }
  }
}

enum WeatherCondition { sunny, cloudy, rainy, windy }

class QuickAccess {
  final String id;
  final String title;
  final String titleAr;
  final IconData icon;
  final Color color;
  final String route;

  QuickAccess({
    required this.id,
    required this.title,
    required this.titleAr,
    required this.icon,
    required this.color,
    required this.route,
  });
}

// Healthcare models
class HealthcareData {
  final List<EmergencyContact> emergencyContacts;
  final List<MedicalService> medicalServices;
  final List<PrayerRoom> prayerRooms;
  final List<FamilyFacility> familyFacilities;
  final List<String> lostAndFoundInfo;
  final List<String> lostAndFoundInfoAr;
  final List<HealthTip> healthTips;

  HealthcareData({
    required this.emergencyContacts,
    required this.medicalServices,
    required this.prayerRooms,
    required this.familyFacilities,
    required this.lostAndFoundInfo,
    required this.lostAndFoundInfoAr,
    required this.healthTips,
  });
}

class EmergencyContact {
  final String name;
  final String nameAr;
  final String number;
  final String? description;
  final String? descriptionAr;
  final String priority;
  final IconData icon;

  EmergencyContact({
    required this.name,
    required this.nameAr,
    required this.number,
    this.description,
    this.descriptionAr,
    required this.priority,
    required this.icon,
  });
}

class MedicalService {
  final String title;
  final String titleAr;
  final String description;
  final String descriptionAr;
  final IconData icon;
  final Color color;
  final List<String> details;
  final List<String> detailsAr;
  final Map<String, String> operatingHours;

  MedicalService({
    required this.title,
    required this.titleAr,
    required this.description,
    required this.descriptionAr,
    required this.icon,
    required this.color,
    required this.details,
    required this.detailsAr,
    required this.operatingHours,
  });
}

class PrayerRoom {
  final String name;
  final String nameAr;
  final String location;
  final String locationAr;
  final String capacity;
  final String capacityAr;
  final List<String> facilities;
  final List<String> facilitiesAr;
  final Map<String, String> prayerTimes;

  PrayerRoom({
    required this.name,
    required this.nameAr,
    required this.location,
    required this.locationAr,
    required this.capacity,
    required this.capacityAr,
    required this.facilities,
    required this.facilitiesAr,
    required this.prayerTimes,
  });
}

class FamilyFacility {
  final String name;
  final String nameAr;
  final String description;
  final String descriptionAr;
  final IconData icon;
  final Color color;
  final List<String> features;
  final List<String> featuresAr;
  final String location;
  final String locationAr;

  FamilyFacility({
    required this.name,
    required this.nameAr,
    required this.description,
    required this.descriptionAr,
    required this.icon,
    required this.color,
    required this.features,
    required this.featuresAr,
    required this.location,
    required this.locationAr,
  });
}

class HealthTip {
  final String title;
  final String titleAr;
  final String description;
  final String descriptionAr;
  final List<String> tips;
  final List<String> tipsAr;
  final IconData icon;
  final Color color;
  final String importance;

  HealthTip({
    required this.title,
    required this.titleAr,
    required this.description,
    required this.descriptionAr,
    required this.tips,
    required this.tipsAr,
    required this.icon,
    required this.color,
    required this.importance,
  });
}

class FirstAidStation {
  final String id;
  final String name;
  final String nameAr;
  final String type;
  final String location;
  final String locationAr;
  final double latitude;
  final double longitude;
  final List<String> services;
  final List<String> servicesAr;
  final int staffCount;
  final String? contactNumber;
  final bool isOpen;

  FirstAidStation({
    required this.id,
    required this.name,
    required this.nameAr,
    required this.type,
    required this.location,
    required this.locationAr,
    required this.latitude,
    required this.longitude,
    required this.services,
    required this.servicesAr,
    required this.staffCount,
    this.contactNumber,
    required this.isOpen,
  });
}

class EmergencyInfo {
  final List<EmergencyProcedure> procedures;
  final List<EvacuationPoint> evacuationPoints;
  final List<EmergencyContact> contacts;
  final List<SafetyTip> safetyTips;

  EmergencyInfo({
    required this.procedures,
    required this.evacuationPoints,
    required this.contacts,
    required this.safetyTips,
  });
}

class EmergencyProcedure {
  final String title;
  final String titleAr;
  final String description;
  final String descriptionAr;
  final IconData icon;
  final Color color;
  final List<String> steps;
  final List<String> stepsAr;

  EmergencyProcedure({
    required this.title,
    required this.titleAr,
    required this.description,
    required this.descriptionAr,
    required this.icon,
    required this.color,
    required this.steps,
    required this.stepsAr,
  });
}

class EvacuationPoint {
  final String name;
  final String nameAr;
  final String location;
  final String locationAr;
  final double latitude;
  final double longitude;

  EvacuationPoint({
    required this.name,
    required this.nameAr,
    required this.location,
    required this.locationAr,
    required this.latitude,
    required this.longitude,
  });
}

class SafetyTip {
  final String tip;
  final String tipAr;

  SafetyTip({
    required this.tip,
    required this.tipAr,
  });
}


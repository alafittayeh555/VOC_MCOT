<?php
// index.php - Landing Page
session_start();

// Check if user is logged in and redirect employees away from landing page
if (isset($_SESSION['user_id'])) {
    $role_id = $_SESSION['role_id'];
    switch ($role_id) {
        case 1: header("Location: admin/dashboard.php"); exit;
        case 2: header("Location: pr/dashboard.php"); exit;
        case 3: header("Location: department/dashboard.php"); exit;
        case 5: header("Location: employee/dashboard.php"); exit;
        // Role 4 (General User) stays on this page
    }
}

// Include language handler
require_once 'includes/language_handler.php';
require_once 'config/database.php';

$db = Database::connect();
// 1. Auto-Installation: Check if table exists (Self-healing for index page too if admin hasn't been visited)
try {
    $db->query("SELECT 1 FROM images LIMIT 1");
} catch (PDOException $e) {
    // If missing, we can just supply an empty array or handle gracefully, 
    // but better to catch it so it doesn't crash if admin didn't run first.
    // Ideally admin page runs migration. For now, we assume admin page was visited or we handle empty.
}

// Fetch page settings for text overrides
$page_settings = [];
try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM page_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $page_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    // Table might not exist yet if admin hasn't been visited
}

// Fetch active images (Banners only)
try {
    $images = $db->query("SELECT * FROM images WHERE is_active = 1 AND type = 'banner' ORDER BY display_order ASC, created_at DESC")->fetchAll();
} catch (Exception $e) {
    $images = [];
}

// Fallback if no images
if (empty($images)) {
    $images[] = [
        'title' => __('index_hero_title', 'ระบบรับเรื่องร้องเรียนและข้อเสนอแนะ'),
        'subtitle' => __('index_hero_subtitle', 'องค์การกระจายเสียงและแพร่ภาพสาธารณะแห่งประเทศไทย'),
        'link' => '#complaint',
        'image_path' => null // Use default CSS background
    ];
}

// Fetch active Process 2 images
try {
    $process2_images = $db->query("SELECT * FROM images WHERE is_active = 1 AND type = 'process2' ORDER BY display_order ASC, created_at DESC")->fetchAll();
} catch (Exception $e) {
    $process2_images = [];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo (isset($curr_lang) && $curr_lang == 'en') ? 'VOC System – MCOT' : 'ระบบรับเรื่องร้องเรียน อสมท'; ?></title>
    <!-- Favicon -->
    <link rel="icon" type="image/jpeg" href="assets/img/logo/logo-mcot.jpeg">
    
    <!-- Boxicons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    
    <!-- Google Fonts: Prompt + Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/landing.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Header / Navigation -->
    <?php 
    $is_index_page = true;
    require_once 'includes/header_landing.php'; 
    ?>

    <!-- Hero Carousel Section -->
    <section class="hero-section !mb-0 pb-0 flex flex-col" id="home">
        <div class="carousel w-full">
            <div class="carousel-inner">
                <?php foreach ($images as $index => $image): ?>
                <?php 
                    $display_image = $image['image_path'] ?? null;
                    if ($curr_lang === 'en' && !empty($image['image_path_en'])) {
                        $display_image = $image['image_path_en'];
                    }
                ?>
                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                    <?php if ($display_image): ?>
                        <img src="<?php echo htmlspecialchars($display_image); ?>" alt="<?php echo htmlspecialchars($image['title'] ?? ''); ?>" class="banner-img">
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="carousel-indicators">
                <?php foreach ($images as $index => $image): ?>
                <span class="indicator <?php echo $index === 0 ? 'active' : ''; ?>" onclick="currentSlide(<?php echo $index + 1; ?>)"></span>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Features/Process Section (Redesigned) -->
    <?php
        $proc_bg        = $page_settings['procedure_bg_color']       ?? '#142A51';
        $proc_card_bg   = $page_settings['procedure_card_bg_color']  ?? '#ffffff';
        $proc_title_clr = $page_settings['procedure_title_color']    ?? '#ffffff';
        $proc_text_clr  = $page_settings['procedure_text_color']     ?? '#1D2B4F';
        $proc_icon_clr  = $page_settings['procedure_icon_color']     ?? '#0056FF';
        $proc_btn_txt   = $page_settings['procedure_btn_text_color'] ?? '#0056FF';
        // Icon circle background: a light tint derived from the card bg (kept as css variable-like override)
        $proc_icon_bg   = $page_settings['procedure_card_bg_color']  ?? '#F4F7FB';
    ?>
    <section class="py-16 md:py-24" id="complaint" style="background-color: <?php echo htmlspecialchars($proc_bg); ?>;">
        <div class="container mx-auto px-4 max-w-[1200px]">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-[36px] font-bold tracking-tight" style="color: <?php echo htmlspecialchars($proc_title_clr); ?>;">
                    <?php echo __('index_card_process_title', 'ขั้นตอนการปฏิบัติ'); ?>
                </h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8">
                <!-- Card 1 -->
                <div class="proc-card-hover rounded-2xl p-8 text-center flex flex-col items-center relative pb-24" style="background-color: <?php echo htmlspecialchars($proc_card_bg); ?>;">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mb-6" style="background-color: <?php echo htmlspecialchars($proc_icon_clr); ?>1A; color: <?php echo htmlspecialchars($proc_icon_clr); ?>;">
                        <i class='bx bx-edit text-3xl'></i>
                    </div>
                    <h3 class="text-[20px] font-bold mb-4" style="color: <?php echo htmlspecialchars($proc_text_clr); ?>;">
                        <?php echo __('index_card_1_title', 'ร้องเรียน / ข้อเสนอแนะ'); ?>
                    </h3>
                    <p class="text-sm leading-relaxed mb-6" style="color: <?php echo htmlspecialchars($proc_text_clr); ?>; opacity: 0.75;">
                        <?php echo __('index_card_1_desc', 'แจ้งเรื่องร้องเรียน หรือเสนอแนะความคิดเห็นต่างๆ'); ?>
                    </p>
                    <?php $link_complaint = isset($_SESSION['user_id']) ? 'user/Complaint_Suggestion.php' : 'login.php?redirect=' . urlencode('user/Complaint_Suggestion.php'); ?>
                    <a href="<?php echo $link_complaint; ?>" class="absolute bottom-8 px-6 py-2 font-bold rounded-lg transition-colors duration-300" style="background-color: <?php echo htmlspecialchars($proc_icon_clr); ?>1A; color: <?php echo htmlspecialchars($proc_btn_txt); ?>;">
                        <?php echo __('index_card_1_btn', 'คลิกที่นี่'); ?>
                    </a>
                </div>
                
                <!-- Card 2 -->
                <div class="proc-card-hover rounded-2xl p-8 text-center flex flex-col items-center relative pb-24" style="background-color: <?php echo htmlspecialchars($proc_card_bg); ?>;">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mb-6" style="background-color: <?php echo htmlspecialchars($proc_icon_clr); ?>1A; color: <?php echo htmlspecialchars($proc_icon_clr); ?>;">
                        <i class='bx bx-search-alt text-3xl'></i>
                    </div>
                    <h3 class="text-[20px] font-bold mb-4" style="color: <?php echo htmlspecialchars($proc_text_clr); ?>;">
                        <?php echo __('index_card_2_title', 'สถานะ'); ?>
                    </h3>
                    <p class="text-sm leading-relaxed mb-6" style="color: <?php echo htmlspecialchars($proc_text_clr); ?>; opacity: 0.75;">
                        <?php echo __('index_card_2_desc', 'ติดตามสถานะเรื่องร้องเรียนของคุณ'); ?>
                    </p>
                    <?php $link_status = isset($_SESSION['user_id']) ? 'user/status.php' : 'login.php?redirect=' . urlencode('user/status.php'); ?>
                    <a href="<?php echo $link_status; ?>" class="absolute bottom-8 px-6 py-2 font-bold rounded-lg transition-colors duration-300" style="background-color: <?php echo htmlspecialchars($proc_icon_clr); ?>1A; color: <?php echo htmlspecialchars($proc_btn_txt); ?>;">
                        <?php echo __('index_card_2_btn', 'ตรวจสอบ'); ?>
                    </a>
                </div>
                
                <!-- Card 3 -->
                <div class="proc-card-hover rounded-2xl p-8 text-center flex flex-col items-center relative pb-24" style="background-color: <?php echo htmlspecialchars($proc_card_bg); ?>;">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mb-6" style="background-color: <?php echo htmlspecialchars($proc_icon_clr); ?>1A; color: <?php echo htmlspecialchars($proc_icon_clr); ?>;">
                        <i class='bx bx-history text-3xl'></i>
                    </div>
                    <h3 class="text-[20px] font-bold mb-4" style="color: <?php echo htmlspecialchars($proc_text_clr); ?>;">
                        <?php echo __('index_card_3_title', 'ประวัติ'); ?>
                    </h3>
                    <p class="text-sm leading-relaxed mb-6" style="color: <?php echo htmlspecialchars($proc_text_clr); ?>; opacity: 0.75;">
                        <?php echo __('index_card_3_desc', 'ดูประวัติการร้องเรียนทั้งหมดของคุณ'); ?>
                    </p>
                    <?php $link_history = isset($_SESSION['user_id']) ? 'user/history.php' : 'login.php?redirect=' . urlencode('user/history.php'); ?>
                    <a href="<?php echo $link_history; ?>" class="absolute bottom-8 px-6 py-2 font-bold rounded-lg transition-colors duration-300" style="background-color: <?php echo htmlspecialchars($proc_icon_clr); ?>1A; color: <?php echo htmlspecialchars($proc_btn_txt); ?>;">
                        <?php echo __('index_card_3_btn', 'ดูประวัติ'); ?>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Process Section 2 -->
    <?php
        $p2_bg    = $page_settings['process2_bg_color']    ?? '#ffffff';
        $p2_title = $page_settings['process2_title_color'] ?? '#1D2B4F';
    ?>
    <section class="py-16 md:py-24 overflow-hidden" id="process2" style="background-color: <?php echo htmlspecialchars($p2_bg); ?>;">
        <div class="w-full max-w-full mx-auto px-2 md:px-6 lg:px-8">
            <!-- Section Header -->
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-[42px] font-bold tracking-tight" style="color: <?php echo htmlspecialchars($p2_title); ?>;">
                    <?php echo __('index_card_process2_title', 'แนวทางการปฏิบัติ'); ?>
                </h2>
            </div>
            
            <!-- Cards Container -->
            <div class="relative w-full group px-0">
                <!-- Left/Right Arrows (Desktop) -->
                <button id="process2PrevBtn" class="absolute left-[-5px] md:left-2 lg:left-6 xl:left-8 top-1/2 -translate-y-1/2 w-12 h-12 bg-white rounded-full shadow-[0_4px_16px_rgba(0,0,0,0.15)] flex items-center justify-center text-[#1D2B4F] hover:text-gray-900 z-20 hidden lg:flex transition-transform hover:scale-105" aria-label="Previous">
                    <i class='bx bx-chevron-left text-3xl'></i>
                </button>
                <button id="process2NextBtn" class="absolute right-[-5px] md:right-2 lg:right-6 xl:right-8 top-1/2 -translate-y-1/2 w-12 h-12 bg-white rounded-full shadow-[0_4px_16px_rgba(0,0,0,0.15)] flex items-center justify-center text-[#1D2B4F] hover:text-gray-900 z-20 hidden lg:flex transition-transform hover:scale-105" aria-label="Next">
                    <i class='bx bx-chevron-right text-3xl'></i>
                </button>

                <!-- Scrollable Wrapper -->
                <div id="process2ScrollWrapper" class="flex overflow-x-auto snap-x snap-mandatory gap-4 md:gap-6 pb-8 hide-scrollbar px-1" style="scroll-behavior: smooth;">
                    
                    <?php if (!empty($process2_images)): ?>
                        <?php foreach ($process2_images as $index => $p_image): ?>
                            <div class="snap-start shrink-0 w-[90vw] md:w-[590px] h-auto md:h-[380px] bg-[#F4F7FB] rounded-[24px] flex relative transition-all duration-300 hover:shadow-md border border-gray-50/50 overflow-hidden cursor-pointer group">
                                <!-- Uploaded Image -->
                                <img src="<?php echo htmlspecialchars($p_image['image_path']); ?>" alt="Process 2 Image" class="w-full h-full object-cover">
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Fallback Placeholder Card 1 -->
                        <div class="snap-start shrink-0 w-[90vw] md:w-[590px] h-auto md:h-[380px] bg-[#F4F7FB] rounded-[24px] flex relative transition-all duration-300 hover:shadow-md border border-gray-50/50 overflow-hidden cursor-pointer group">
                            <!-- Full Image Placeholder -->
                            <div class="w-full h-full min-h-[250px] bg-slate-200/50 flex flex-col items-center justify-center text-slate-400 group-hover:bg-slate-200 transition-colors">
                                <i class='bx bx-image-alt text-7xl mb-4 opacity-50'></i>
                                <span class="text-xl font-medium opacity-60">ใส่รูปภาพ Card 1 (590x380)</span>
                                <span class="text-sm mt-2 opacity-40">index_card2_1_image</span>
                            </div>
                        </div>
                        
                        <!-- Fallback Placeholder Card 2 -->
                        <div class="snap-start shrink-0 w-[90vw] md:w-[590px] h-auto md:h-[380px] bg-[#F4F7FB] rounded-[24px] flex relative transition-all duration-300 hover:shadow-md border border-gray-50/50 overflow-hidden cursor-pointer group">
                            <!-- Full Image Placeholder -->
                            <div class="w-full h-full min-h-[250px] bg-slate-200/50 flex flex-col items-center justify-center text-slate-400 group-hover:bg-slate-200 transition-colors">
                                <i class='bx bx-image-alt text-7xl mb-4 opacity-50'></i>
                                <span class="text-xl font-medium opacity-60">ใส่รูปภาพ Card 2 (590x380)</span>
                                <span class="text-sm mt-2 opacity-40">index_card2_2_image</span>
                            </div>
                        </div>
                        
                        <!-- Fallback Placeholder Card 3 -->
                        <div class="snap-start shrink-0 w-[90vw] md:w-[590px] h-auto md:h-[380px] bg-[#F4F7FB] rounded-[24px] flex relative transition-all duration-300 hover:shadow-md border border-gray-50/50 overflow-hidden cursor-pointer group">
                            <!-- Full Image Placeholder -->
                            <div class="w-full h-full min-h-[250px] bg-slate-200/50 flex flex-col items-center justify-center text-slate-400 group-hover:bg-slate-200 transition-colors">
                                <i class='bx bx-image-alt text-7xl mb-4 opacity-50'></i>
                                <span class="text-xl font-medium opacity-60">ใส่รูปภาพ Card 3 (590x380)</span>
                                <span class="text-sm mt-2 opacity-40">index_card2_3_image</span>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
            
            <!-- Pagination Dots -->
            <div class="flex justify-center mt-2 gap-2" id="process2Dots">
                <?php 
                $total_cards = !empty($process2_images) ? count($process2_images) : 3;
                for($i = 0; $i < $total_cards; $i++): 
                ?>
                <span class="w-2.5 h-2.5 rounded-full dot-indicator cursor-pointer transition-colors duration-300 <?php echo $i === 0 ? 'bg-[#0056FF]' : 'bg-[#D1D5DB] hover:bg-gray-400'; ?>" data-index="<?php echo $i; ?>"></span>
                <?php endfor; ?>
            </div>
        </div>
        
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const wrapper = document.getElementById('process2ScrollWrapper');
                const prevBtn = document.getElementById('process2PrevBtn');
                const nextBtn = document.getElementById('process2NextBtn');
                const dots = document.querySelectorAll('#process2Dots .dot-indicator');
                
                if (wrapper) {
                    const getScrollAmount = () => {
                        const card = wrapper.querySelector('.snap-start');
                        if (card) {
                            const gap = window.innerWidth >= 768 ? 24 : 16;
                            return card.offsetWidth + gap;
                        }
                        return 0;
                    };

                    if (prevBtn) {
                        prevBtn.addEventListener('click', () => {
                            wrapper.scrollBy({ left: -getScrollAmount(), behavior: 'smooth' });
                        });
                    }

                    if (nextBtn) {
                        nextBtn.addEventListener('click', () => {
                            wrapper.scrollBy({ left: getScrollAmount(), behavior: 'smooth' });
                        });
                    }

                    // Update dots on scroll
                    wrapper.addEventListener('scroll', () => {
                        const scrollAmount = getScrollAmount();
                        if (scrollAmount > 0) {
                            const currentIndex = Math.round(wrapper.scrollLeft / scrollAmount);
                            
                            dots.forEach((dot, index) => {
                                if (index === currentIndex) {
                                    dot.classList.remove('bg-[#D1D5DB]', 'hover:bg-gray-400');
                                    dot.classList.add('bg-[#0056FF]');
                                } else {
                                    dot.classList.remove('bg-[#0056FF]');
                                    dot.classList.add('bg-[#D1D5DB]', 'hover:bg-gray-400');
                                }
                            });
                        }
                    });

                    // Click on dot to scroll to card
                    dots.forEach((dot, index) => {
                        dot.addEventListener('click', () => {
                            const scrollAmount = getScrollAmount();
                            wrapper.scrollTo({
                                left: index * scrollAmount,
                                behavior: 'smooth'
                            });
                        });
                    });
                }
            });
        </script>
    </section>

    <!-- About System Section (Premium Redesign) -->
    <?php
        $about_bg = $page_settings['about_bg_color'] ?? '#ffffff';
        $about_text = $page_settings['about_text_color'] ?? '#1F2937';
    ?>
    <section class="about-section premium-about" id="track" style="background-color: <?php echo htmlspecialchars($about_bg); ?>; color: <?php echo htmlspecialchars($about_text); ?>;">
        <div class="container mx-auto">
            <?php 
                $about_title = ($curr_lang === 'en' && !empty($page_settings['index_about_title_en'])) 
                    ? $page_settings['index_about_title_en'] 
                    : ($page_settings['index_about_title'] ?? __('index_about_title', 'เกี่ยวกับระบบ'));
                
                $about_desc = ($curr_lang === 'en' && !empty($page_settings['index_about_desc_en'])) 
                    ? $page_settings['index_about_desc_en'] 
                    : ($page_settings['index_about_desc'] ?? __('index_about_desc', 'ระบบรับเรื่องร้องเรียนและข้อเสนอแนะ เป็นช่องทางสำหรับประชาชนในการแจ้งเรื่องร้องเรียน ข้อเสนอแนะ หรือความคิดเห็นต่างๆ เกี่ยวกับการดำเนินงานของ MCOT เพื่อให้เราสามารถนำไปปรับปรุงและพัฒนาการให้บริการได้อย่างมีประสิทธิภาพยิ่งขึ้น'));
            ?>
            
            <div class="flex flex-col lg:flex-row items-center gap-12 lg:gap-20">
                <!-- Left Content: Text & Info -->
                <div class="lg:w-1/2 space-y-8 reveal-fade-up">
                    <div class="inline-block px-4 py-1.5 rounded-full bg-opacity-10 font-bold text-sm tracking-wider uppercase mb-2" style="background-color: <?php echo htmlspecialchars($about_text); ?>33; color: <?php echo htmlspecialchars($about_text); ?>;">
                        <?php echo $curr_lang === 'en' ? 'Our System' : 'ระบบของเรา'; ?>
                    </div>
                    <h2 class="text-3xl md:text-5xl font-black leading-tight" style="color: <?php echo htmlspecialchars($about_text); ?>;">
                        <?php echo htmlspecialchars($about_title); ?>
                    </h2>
                    <div class="w-20 h-1.5 rounded-full" style="background-color: <?php echo htmlspecialchars($about_text); ?>; opacity: 0.3;"></div>
                    <p class="text-lg md:text-xl leading-relaxed opacity-80" style="color: <?php echo htmlspecialchars($about_text); ?>;">
                        <?php echo nl2br(htmlspecialchars($about_desc)); ?>
                    </p>
                    
                    <div class="pt-4">
                        <a href="#complaint" class="inline-flex items-center gap-2 px-8 py-4 rounded-xl font-bold transition-all duration-300 hover:scale-105 shadow-lg" style="background-color: <?php echo htmlspecialchars($about_text); ?>; color: <?php echo $about_bg; ?>;">
                            <?php echo $curr_lang === 'en' ? 'Get Started' : 'เริ่มต้นใช้งาน'; ?>
                            <i class='bx bx-right-arrow-alt text-xl'></i>
                        </a>
                    </div>
                </div>

                <!-- Right Content: Illustration & Features Grid -->
                <div class="lg:w-1/2 relative reveal-fade-up" style="animation-delay: 0.2s;">
                    <!-- Abstract Illustration -->
                    <div class="relative z-10 rounded-[2rem] overflow-hidden shadow-2xl transform rotate-1 hover:rotate-0 transition-transform duration-500">
                        <img src="assets/img/about_system_illus.png" alt="System Illustration" class="w-full h-auto object-cover">
                    </div>
                    
                    <!-- Floating Feature Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-8">
                        <?php for($i=1; $i<=4; $i++): ?>
                            <?php 
                                $feat_key = "index_feature_$i";
                                $feat_key_en = "index_feature_{$i}_en";
                                $default_text = __($feat_key);
                                
                                $feature_text = ($curr_lang === 'en' && !empty($page_settings[$feat_key_en])) 
                                    ? $page_settings[$feat_key_en] 
                                    : ($page_settings[$feat_key] ?? $default_text);
                                
                                $icons = ['bx-paper-plane', 'bx-tachometer', 'bx-check-double', 'bx-shield-quarter'];
                            ?>
                            <div class="feature-glass-card group p-5 rounded-2xl flex items-center gap-4 transition-all duration-300 hover:-translate-y-1" style="background-color: <?php echo htmlspecialchars($about_text); ?>0D; border: 1px solid <?php echo htmlspecialchars($about_text); ?>1A;">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl transition-transform group-hover:scale-110" style="background-color: <?php echo htmlspecialchars($about_text); ?>1A; color: <?php echo htmlspecialchars($about_text); ?>;">
                                    <i class='bx <?php echo $icons[$i-1]; ?>'></i>
                                </div>
                                <span class="font-bold text-sm md:text-base tracking-tight" style="color: <?php echo htmlspecialchars($about_text); ?>;">
                                    <?php echo htmlspecialchars($feature_text); ?>
                                </span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php
    $footer_bg    = $page_settings['footer_bg_color']   ?? '#04041B';
    $footer_text  = $page_settings['footer_text_color'] ?? '#ffffff';
    $footer_link  = $page_settings['footer_link_color'] ?? $footer_text;
    $footer_border= $page_settings['footer_border_color'] ?? $footer_text;
    ?>
    <footer class="landing-footer" id="contact" style="background-color: <?php echo htmlspecialchars($footer_bg); ?>; padding: 60px 0;">
        <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 15px;">
            <div class="footer-grid" style="display: grid; grid-template-columns: 1fr 1fr 1.2fr 1fr 2fr; gap: 20px; align-items: start;">
                <!-- Column 0: Logo -->
                <div class="footer-col" style="display: flex; justify-content: center;">
                    <img src="assets/img/logo/logo-mcot-removebg-preview.png" alt="MCOT Logo" style="max-width: 140px; height: auto;">
                </div>

                <!-- Column 1: Main Links -->
                <div class="footer-col" style="display: flex; flex-direction: column; gap: 15px; margin-top:2px;">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <?php if (!empty(trim($page_settings['footer_col1_link_' . $i . '_text'] ?? ''))): ?>
                            <a href="<?php echo htmlspecialchars($page_settings['footer_col1_link_' . $i . '_url'] ?? '#'); ?>" target="_blank" style="color: <?php echo htmlspecialchars($footer_link); ?>; font-weight: 700; font-size: 13px; transition: color 0.3s; text-decoration: none; cursor: pointer;">
                                <?php echo htmlspecialchars($page_settings['footer_col1_link_' . $i . '_text']); ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>

                <!-- Column 2: MCOT ACADEMY -->
                <div class="footer-col" style="display: flex; flex-direction: column; gap: 15px;">
                    <a href="#" style="color: <?php echo htmlspecialchars($footer_link); ?>; font-size: 13px; font-weight: 700; text-transform: uppercase; text-decoration: none; cursor: default;">MCOT ACADEMY</a>
                    
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <?php if (!empty(trim($page_settings['footer_col2_link_' . $i . '_text'] ?? ''))): ?>
                            <a href="<?php echo htmlspecialchars($page_settings['footer_col2_link_' . $i . '_url'] ?? '#'); ?>" target="_blank" style="color: <?php echo htmlspecialchars($footer_link); ?>; font-weight: 700; font-size: 13px; transition: color 0.3s; text-decoration: none;">
                                <?php echo htmlspecialchars($page_settings['footer_col2_link_' . $i . '_text']); ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                
                <!-- Column 3: MCOT News -->
                <div class="footer-col" style="display: flex; flex-direction: column; gap: 15px;">
                    <a href="#" style="color: <?php echo htmlspecialchars($footer_link); ?>; font-size: 13px; font-weight: 700; text-decoration: none; cursor: default;">MCOT News</a>
                    
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <?php if (!empty(trim($page_settings['footer_col3_link_' . $i . '_text'] ?? ''))): ?>
                            <a href="<?php echo htmlspecialchars($page_settings['footer_col3_link_' . $i . '_url'] ?? '#'); ?>" target="_blank" style="color: <?php echo htmlspecialchars($footer_link); ?>; font-weight: 700; font-size: 13px; transition: color 0.3s; text-decoration: none;">
                                <?php echo htmlspecialchars($page_settings['footer_col3_link_' . $i . '_text']); ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                
                <!-- Column 4: Texts and Socials -->
                <div class="footer-col">
                    <div style="line-height: 2; color: <?php echo htmlspecialchars($footer_text); ?>; font-size: 13px; font-weight: 500;">
                        <span style="display: block;"><?php echo htmlspecialchars($page_settings['footer_company_name'] ?? 'บริษัท อสมท จำกัด (มหาชน)'); ?></span>
                        <span style="display: block;"><?php echo htmlspecialchars($page_settings['footer_address'] ?? '63/1 ถ.พระราม 9 ห้วยขวาง กทม. 10310'); ?></span>
                        <span style="display: block;">Email: <?php echo htmlspecialchars($page_settings['footer_contact_email_1'] ?? 'contact@mcot.net'); ?></span>
                        <span style="display: block;"><?php echo htmlspecialchars($page_settings['footer_contact_ads'] ?? 'ติดต่อโฆษณา: 02-201-6155'); ?></span>
                        <span style="display: block;"><?php echo htmlspecialchars($page_settings['footer_contact_email_2'] ?? 'Email: contact@mcot.net'); ?></span>
                        <span style="display: block;"><?php echo htmlspecialchars($page_settings['footer_contact_tel'] ?? 'Tel: 02-201-6155'); ?></span>
                    </div>

                    <hr style="border: 0; border-top: 1px solid <?php echo htmlspecialchars($footer_border); ?>; margin: 15px 0; width: 100%;">

                    <div class="social-links" style="display: flex; gap: 12px;">
                        <a href="<?php echo htmlspecialchars($page_settings['social_facebook'] ?? '#'); ?>" target="_blank" style="display: flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 50%; border: 1.5px solid <?php echo htmlspecialchars($footer_border); ?>; color: <?php echo htmlspecialchars($footer_link); ?>; font-size: 18px; transition: all 0.3s; text-decoration: none;">
                            <i class='bx bxl-facebook'></i>
                        </a>
                        <a href="<?php echo htmlspecialchars($page_settings['social_tiktok'] ?? '#'); ?>" target="_blank" style="display: flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 50%; border: 1.5px solid <?php echo htmlspecialchars($footer_border); ?>; color: <?php echo htmlspecialchars($footer_link); ?>; font-size: 18px; transition: all 0.3s; text-decoration: none;">
                            <i class='bx bxl-tiktok'></i>
                        </a>
                        <a href="<?php echo htmlspecialchars($page_settings['social_instagram'] ?? '#'); ?>" target="_blank" style="display: flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 50%; border: 1.5px solid <?php echo htmlspecialchars($footer_border); ?>; color: <?php echo htmlspecialchars($footer_link); ?>; font-size: 18px; transition: all 0.3s; text-decoration: none;">
                            <i class='bx bxl-instagram'></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </footer>


    <script>
        // Scroll effect: shrink header
        const header = document.querySelector('.landing-header');
        if (header) {
            window.addEventListener('scroll', () => {
                if (window.scrollY > 40) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            });
        }
    </script>
</body>
</html>
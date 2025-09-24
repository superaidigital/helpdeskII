<?php
// index.php
session_start();

// Redirect logged-in users to their respective dashboards
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    if ($role === 'admin') {
        header("Location: admin_dashboard.php");
    } elseif ($role === 'it') {
        header("Location: it_dashboard.php");
    } elseif ($role === 'user') {
        header("Location: user_dashboard.php");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบแจ้งปัญหาและให้คำปรึกษา - อบจ.ศรีสะเกษ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        /* Animated Gradient Background */
        body {
            background: linear-gradient(-45deg, #667eea, #764ba2, #8ec5fc, #e0c3fc);
            background-size: 400% 400%;
            animation: gradientBG 18s ease infinite;
        }
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Glassmorphism Card Effect */
        .glass-card {
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Entrance Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in {
            opacity: 0;
            animation: fadeIn 0.6s ease-out forwards;
        }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }
        .delay-5 { animation-delay: 0.5s; }
        .delay-6 { animation-delay: 0.6s; }

        /* Button Hover Effect */
        .menu-button {
            transition: all 0.3s ease;
        }
        .menu-button:hover {
            transform: translateY(-4px) scale(1.03);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="font-sans">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md p-8 space-y-6 glass-card rounded-2xl shadow-2xl">
            <div class="text-center space-y-2">
                <img src="assets/images/LogoSSKPao.png" alt="Logo" class="h-24 w-24 mx-auto fade-in delay-1">
                <h1 class="text-2xl font-bold text-gray-800 fade-in delay-2">อบจ.ศรีสะเกษ</h1>
                <h2 class="text-xl font-semibold text-gray-700 fade-in delay-3">ระบบแจ้งปัญหาและให้คำปรึกษาด้าน IT</h2>
            </div>
            
            <div class="space-y-4">
                <a href="public_form.php" class="menu-button w-full flex items-center justify-center px-4 py-3 font-bold text-white bg-green-500 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 fade-in delay-4">
                    <i class="fa-solid fa-bullhorn mr-2"></i> แจ้งปัญหา (ไม่ต้องล็อกอิน)
                </a>
                
                <a href="track_issue.php" class="menu-button w-full flex items-center justify-center px-4 py-3 font-bold text-indigo-700 bg-indigo-100 rounded-lg hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 fade-in delay-5">
                    <i class="fa-solid fa-search mr-2"></i> ติดตามสถานะเรื่อง
                </a>

                <a href="contact_page.php" class="menu-button w-full flex items-center justify-center px-4 py-3 font-bold text-blue-700 bg-blue-100 rounded-lg hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 fade-in delay-6">
                    <i class="fa-solid fa-address-book mr-2"></i> ติดต่อเจ้าหน้าที่
                </a>
            </div>

             <div class="relative fade-in delay-6" x-data="{ isLoginOpen: false }">
                <div @click="isLoginOpen = !isLoginOpen" class="cursor-pointer relative flex py-2 items-center">
                    <div class="flex-grow border-t border-gray-400"></div>
                    <span class="flex-shrink mx-4 text-gray-600 text-sm font-medium">สำหรับเจ้าหน้าที่ / สมาชิก</span>
                    <div class="flex-grow border-t border-gray-400"></div>
                    <i class="fa-solid fa-chevron-down text-gray-500 absolute right-0 transition-transform" :class="{'rotate-180': isLoginOpen}"></i>
                </div>

                <div x-show="isLoginOpen" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-2" class="pt-4">
                     <?php 
                        if (isset($_SESSION['flash_message'])) {
                            $type = $_SESSION['flash_message']['type'] === 'success' ? 'green' : 'red';
                            echo '<div class="bg-'.$type.'-100 border border-'.$type.'-400 text-'.$type.'-700 px-4 py-3 rounded relative mb-4" role="alert">';
                            echo '<span>' . htmlspecialchars($_SESSION['flash_message']['message']) . '</span>';
                            echo '</div>';
                            unset($_SESSION['flash_message']);
                        }
                    ?>
                    <form action="login_process.php" method="POST" class="space-y-4">
                        <div>
                            <label for="email" class="sr-only">อีเมล</label>
                            <input id="email" name="email" type="email" required class="w-full px-4 py-3 mt-1 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="อีเมล">
                        </div>
                        <div>
                            <label for="password" class="sr-only">รหัสผ่าน</label>
                            <input id="password" name="password" type="password" required class="w-full px-4 py-3 mt-1 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="รหัสผ่าน">
                        </div>
                        <div>
                            <button type="submit" class="w-full px-4 py-3 font-bold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                เข้าสู่ระบบ
                            </button>
                        </div>
                    </form>
                </div>
            </div>

             <div class="text-center text-sm text-gray-700 fade-in delay-6">
                ยังไม่มีบัญชี? <a href="register.php" class="font-medium text-indigo-600 hover:text-indigo-500">สมัครสมาชิก</a>
            </div>
        </div>
    </div>
</body>
</html>
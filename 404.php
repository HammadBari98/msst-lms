<?php
header("HTTP/1.0 404 Not Found");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <!-- Tailwind CSS (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome (for icons) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom Animation (optional) -->
    <style>
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        .floating { animation: float 3s ease-in-out infinite; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-purple-50 min-h-screen flex items-center justify-center">
    <div class="max-w-2xl mx-4 text-center">
        <!-- Animated 404 Illustration -->
        <div class="floating mb-8">
            <svg class="w-40 h-40 mx-auto" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                <path fill="#4F46E5" d="M40,-58.3C52.5,-50.1,63.8,-39.8,68.2,-26.7C72.6,-13.5,70.1,2.5,64.5,17.1C58.9,31.7,50.2,45,37.7,53.8C25.2,62.6,8.9,66.9,-6.9,69.6C-22.7,72.3,-45.4,73.4,-58.3,64.5C-71.2,55.6,-74.3,36.7,-74.2,18.7C-74.1,0.7,-70.8,-16.5,-62.1,-31.9C-53.4,-47.4,-39.3,-61.2,-24.8,-68.6C-10.3,-76,4.6,-77.1,18.7,-72.9C32.8,-68.7,46.1,-59.3,40,-58.3Z" transform="translate(100 100)" />
            </svg>
        </div>

        <!-- Error Message -->
        <h1 class="text-5xl font-bold text-gray-800 mb-4">404</h1>
        <h2 class="text-2xl font-semibold text-gray-700 mb-6">Oops! Page Not Found</h2>
        <p class="text-gray-600 mb-8">The page you're looking for doesn't exist or has been moved.</p>

        <!-- Action Buttons -->
        <div class="flex flex-wrap justify-center gap-4">
            <a href="/" class="px-6 py-3 bg-indigo-600 text-white rounded-lg shadow-md hover:bg-indigo-700 transition duration-300 flex items-center">
                <i class="fas fa-home mr-2"></i> Go Home
            </a>
            <a href="mailto:support@yourdomain.com" class="px-6 py-3 bg-white text-gray-700 border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 transition duration-300 flex items-center">
                <i class="fas fa-envelope mr-2"></i> Contact Support
            </a>
        </div>

        <!-- Search Bar (optional) -->
        <div class="mt-10 max-w-md mx-auto">
            <form action="/search" method="GET" class="flex">
                <input type="text" name="q" placeholder="Search..." class="flex-grow px-4 py-2 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-r-lg hover:bg-indigo-700 transition duration-300">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
    </div>
</body>
</html>
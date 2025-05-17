<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mystica Occulta - Haute Spiritualité et Rituels Ancestraux</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700;900&family=MedievalSharp&family=Merriweather:ital,wght@0,300;0,400;0,700;0,900;1,300;1,400;1,700;1,900&display=swap');
        
        :root {
            --primary: #3a0ca3;
            --secondary: #7209b7;
            --accent: #f72585;
            --dark: #1a1a2e;
            --light: #f8f9fa;
        }
        
        body {
            font-family: 'Merriweather', serif;
            background-color: #0f0e17;
            color: #e8e8e8;
            scroll-behavior: smooth;
        }
        
        .font-cinzel {
            font-family: 'Cinzel Decorative', cursive;
        }
        
        .font-medieval {
            font-family: 'MedievalSharp', cursive;
        }
        
        .bg-mystic {
            background: radial-gradient(circle at center, #3a0ca3 0%, #1a1a2e 70%);
        }
        
        .hero-overlay {
            background: rgba(0, 0, 0, 0.7);
        }
        
        .ritual-card {
            transition: all 0.3s ease;
            background: linear-gradient(145deg, #1a1a2e 0%, #16213e 100%);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }
        
        .ritual-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(247, 37, 133, 0.4);
        }
        
        .category-icon {
            transition: all 0.3s ease;
        }
        
        .category-icon:hover {
            transform: scale(1.2) rotate(10deg);
            filter: drop-shadow(0 0 10px var(--accent));
        }
        
        .testimonial-card {
            background: linear-gradient(145deg, #16213e 0%, #1a1a2e 100%);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .nav-link {
            position: relative;
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: var(--accent);
            transition: width 0.3s ease;
        }
        
        .nav-link:hover::after {
            width: 100%;
        }
        
        .btn-magic {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            box-shadow: 0 4px 15px rgba(247, 37, 133, 0.4);
            transition: all 0.3s ease;
        }
        
        .btn-magic:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 20px rgba(247, 37, 133, 0.6);
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(247, 37, 133, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(247, 37, 133, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(247, 37, 133, 0);
            }
        }
        
        .floating {
            animation: floating 6s ease-in-out infinite;
        }
        
        @keyframes floating {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0px); }
        }
        
        .glow-text {
            text-shadow: 0 0 10px rgba(247, 37, 133, 0.7);
        }
        
        .candle-flicker {
            animation: flicker 3s infinite alternate;
        }
        
        @keyframes flicker {
            0%, 18%, 22%, 25%, 53%, 57%, 100% {
                filter: drop-shadow(0 0 10px rgba(247, 37, 133, 0.7));
            }
            20%, 24%, 55% {
                filter: drop-shadow(0 0 5px rgba(247, 37, 133, 0.3));
            }
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.8);
        }
        
        .modal-content {
            background: linear-gradient(145deg, #1a1a2e 0%, #16213e 100%);
            margin: 5% auto;
            padding: 30px;
            border: 1px solid var(--accent);
            width: 80%;
            max-width: 800px;
            border-radius: 10px;
            box-shadow: 0 0 30px rgba(247, 37, 133, 0.5);
            animation: modalFadeIn 0.3s;
        }
        
        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-50px);}
            to {opacity: 1; transform: translateY(0);}
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: var(--accent);
        }
    </style>
</head>
<body class="bg-dark text-light">
    <!-- Header -->
    <header class="fixed w-full z-50 bg-dark bg-opacity-90 backdrop-blur-sm border-b border-purple-900">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-900 to-pink-600 flex items-center justify-center">
                    <i class="fas fa-eye text-white text-xl"></i>
                </div>
                <a href="#" class="font-cinzel text-2xl font-bold bg-gradient-to-r from-purple-400 to-pink-600 bg-clip-text text-transparent">MYSTICA OCCULTA</a>
            </div>
            
            <nav class="hidden md:flex space-x-8">
                <a href="#home" class="nav-link text-gray-300 hover:text-white">Accueil</a>
                <a href="#rituals" class="nav-link text-gray-300 hover:text-white">Rituels & Magie</a>
                <a href="#vodoun" class="nav-link text-gray-300 hover:text-white">Vodoun</a>
                <a href="#prosperity" class="nav-link text-gray-300 hover:text-white">Prospérité</a>
                <a href="#love" class="nav-link text-gray-300 hover:text-white">Amour</a>
                <a href="#shop" class="nav-link text-gray-300 hover:text-white">Boutique</a>
                <a href="#testimonials" class="nav-link text-gray-300 hover:text-white">Témoignages</a>
                <a href="#blog" class="nav-link text-gray-300 hover:text-white">Blog</a>
            </nav>
            
            <div class="flex items-center space-x-4">
                <a href="#contact" class="btn-magic px-4 py-2 rounded-full text-white font-medium hidden md:block">Consultation</a>
                <button class="md:hidden text-gray-300 hover:text-white focus:outline-none" id="mobileMenuButton">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div class="md:hidden hidden absolute top-16 left-0 right-0 bg-dark p-4 border-t border-purple-900" id="mobileMenu">
            <div class="flex flex-col space-y-4">
                <a href="#home" class="text-gray-300 hover:text-white">Accueil</a>
                <a href="#rituals" class="text-gray-300 hover:text-white">Rituels & Magie</a>
                <a href="#vodoun" class="text-gray-300 hover:text-white">Vodoun</a>
                <a href="#prosperity" class="text-gray-300 hover:text-white">Prospérité</a>
                <a href="#love" class="text-gray-300 hover:text-white">Amour</a>
                <a href="#shop" class="text-gray-300 hover:text-white">Boutique</a>
                <a href="#testimonials" class="text-gray-300 hover:text-white">Témoignages</a>
                <a href="#blog" class="text-gray-300 hover:text-white">Blog</a>
                <a href="#contact" class="btn-magic px-4 py-2 rounded-full text-white font-medium text-center">Consultation</a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section id="home" class="relative h-screen flex items-center justify-center overflow-hidden" style="background-image: url('<?php echo htmlspecialchars($site_images['background_main']); ?>'); background-size: cover; background-position: center;">
        <div class="absolute inset-0 hero-overlay"></div>
        <div class="absolute inset-0 flex items-center justify-center opacity-20">
            <div class="w-64 h-64 rounded-full bg-purple-900 filter blur-3xl"></div>
        </div>
        
        <div class="container mx-auto px-4 relative z-10 text-center">
            <div class="max-w-4xl mx-auto">
                <h1 class="font-cinzel text-4xl md:text-6xl font-bold mb-6 text-white">
                    <span class="glow-text">Transformez Votre Vie</span> <br>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-pink-600">Par Des Rituels Ancestraux</span>
                </h1>
                <p class="text-xl md:text-2xl mb-10 text-gray-300 max-w-3xl mx-auto">
                    Découvrez la puissance des rituels magiques authentiques transmis par les grands maîtres depuis des siècles.
                </p>
                <div class="flex flex-col sm:flex-row justify-center gap-4">
                    <a href="#rituals" class="btn-magic px-8 py-4 rounded-full text-white font-bold text-lg flex items-center justify-center space-x-2">
                        <span>Explorer les Rituels</span>
                        <i class="fas fa-arrow-down"></i>
                    </a>
                    <a href="#contact" class="px-8 py-4 rounded-full border-2 border-purple-600 text-white font-bold text-lg hover:bg-purple-900 transition duration-300 flex items-center justify-center space-x-2">
                        <span>Consultation Privée</span>
                        <i class="fas fa-star"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="absolute bottom-10 left-1/2 transform -translate-x-1/2 animate-bounce">
            <a href="#rituals" class="text-white text-2xl">
                <i class="fas fa-chevron-down"></i>
            </a>
        </div>
        
        <!-- Floating elements -->
        <div class="absolute top-20 left-20 w-16 h-16 rounded-full bg-purple-800 opacity-20 floating"></div>
        <div class="absolute bottom-1/4 right-32 w-24 h-24 rounded-full bg-pink-800 opacity-20 floating" style="animation-delay: 1s;"></div>
        <div class="absolute top-1/3 right-1/4 w-20 h-20 rounded-full bg-indigo-800 opacity-20 floating" style="animation-delay: 2s;"></div>
    </section>

    <!-- Featured Rituals -->
    <section id="rituals" class="py-20 bg-dark">
        <div class="container mx-auto px-4">
            <div class="text-center mb-16">
                <h2 class="font-cinzel text-3xl md:text-5xl font-bold mb-4 text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-pink-600">
                    Rituels Puissants du Moment
                </h2>
                <div class="w-24 h-1 bg-gradient-to-r from-purple-500 to-pink-500 mx-auto mb-6"></div>
                <p class="text-gray-400 max-w-2xl mx-auto">
                    Ces rituels sélectionnés avec soin ont démontré une efficacité exceptionnelle pour nos clients.
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Ritual 1 -->
                <div class="ritual-card rounded-xl p-6 border border-purple-900">
                    <div class="mb-6 relative">
                        <div class="w-full h-48 rounded-lg bg-gradient-to-br from-purple-900 to-pink-900 flex items-center justify-center">
                            <i class="fas fa-moon text-6xl text-white candle-flicker"></i>
                        </div>
                        <div class="absolute -top-4 -right-4 bg-pink-600 text-white rounded-full w-12 h-12 flex items-center justify-center font-bold">
                            <span>NEW</span>
                        </div>
                    </div>
                    <h3 class="font-cinzel text-2xl font-bold mb-3 text-white">Rituel du Miroir Noir</h3>
                    <p class="text-gray-400 mb-4">
                        Un rituel ancestral de protection et de domination utilisant les énergies lunaires pour créer un bouclier imp

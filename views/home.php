<?php
include_once 'product.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Pris</title>

    <!-- Self includes -->
    <link rel="stylesheet" href="aside.css">

    <!-- External includes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Changa+One:ital@0;1&display=swap" rel="stylesheet">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
</head>

<body>
    <aside id="aside" class="aside">
        <header class="menuheader">
            <button class="closemenubutton" onclick="Toggle()">
                <div class="bar1"></div>
                <div class="bar2"></div>
                <div class="bar3"></div>
            </button>
            <h2 class="menuappname">PrisYa</h2>
        </header>
        <nav id="menu" class="menu">
            <a href="/" class="menuitem current">
                <svg class="menuicon" xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" viewBox="0 0 48 48">
                    <path
                        d="M39.5,43h-9c-1.381,0-2.5-1.119-2.5-2.5v-9c0-1.105-0.895-2-2-2h-4c-1.105,0-2,0.895-2,2v9c0,1.381-1.119,2.5-2.5,2.5h-9	C7.119,43,6,41.881,6,40.5V21.413c0-2.299,1.054-4.471,2.859-5.893L23.071,4.321c0.545-0.428,1.313-0.428,1.857,0L39.142,15.52	C40.947,16.942,42,19.113,42,21.411V40.5C42,41.881,40.881,43,39.5,43z">
                    </path>
                </svg>
                <span class="menuitemtext">Inicio</span>
            </a>
            <a href="/" class="menuitem">
                <svg class="menuicon" xmlns="http://www.w3.org/2000/svg" fill="#000000" width="800px" height="800px"
                    viewBox="0 0 512 512">
                    <path
                        d="M336,256c-20.56,0-40.44-9.18-56-25.84-15.13-16.25-24.37-37.92-26-61-1.74-24.62,5.77-47.26,21.14-63.76S312,80,336,80c23.83,0,45.38,9.06,60.7,25.52,15.47,16.62,23,39.22,21.26,63.63h0c-1.67,23.11-10.9,44.77-26,61C376.44,246.82,356.57,256,336,256Zm66-88h0Z" />
                    <path
                        d="M467.83,432H204.18a27.71,27.71,0,0,1-22-10.67,30.22,30.22,0,0,1-5.26-25.79c8.42-33.81,29.28-61.85,60.32-81.08C264.79,297.4,299.86,288,336,288c36.85,0,71,9,98.71,26.05,31.11,19.13,52,47.33,60.38,81.55a30.27,30.27,0,0,1-5.32,25.78A27.68,27.68,0,0,1,467.83,432Z" />
                    <path
                        d="M147,260c-35.19,0-66.13-32.72-69-72.93C76.58,166.47,83,147.42,96,133.45,108.86,119.62,127,112,147,112s38,7.66,50.93,21.57c13.1,14.08,19.5,33.09,18,53.52C213.06,227.29,182.13,260,147,260Z" />
                    <path
                        d="M212.66,291.45c-17.59-8.6-40.42-12.9-65.65-12.9-29.46,0-58.07,7.68-80.57,21.62C40.93,316,23.77,339.05,16.84,366.88a27.39,27.39,0,0,0,4.79,23.36A25.32,25.32,0,0,0,41.72,400h111a8,8,0,0,0,7.87-6.57c.11-.63.25-1.26.41-1.88,8.48-34.06,28.35-62.84,57.71-83.82a8,8,0,0,0-.63-13.39C216.51,293.42,214.71,292.45,212.66,291.45Z" />
                </svg>
                <span class="menuitemtext">Nosotros</span>
            </a>
            </a>
            <a href="/" class="menuitem">
                <svg class="menuicon" xmlns="http://www.w3.org/2000/svg" fill="#000000" width="800px" height="800px"
                    viewBox="-2 -2 24 24" preserveAspectRatio="xMinYMin" class="jam jam-message-f">
                    <path
                        d="M3 .565h14a3 3 0 0 1 3 3v8a3 3 0 0 1-3 3h-6.958l-6.444 4.808A1 1 0 0 1 2 18.57v-4.006a2 2 0 0 1-2-2v-9a3 3 0 0 1 3-3z" />
                </svg>
                <span class="menuitemtext">Contacto</span>
            </a>
        </nav>
    </aside>

    <div class="content">
        <header class="header">
            <h1>Bienvenido</h1>
        </header>

        <main class="main">
            <header class="catalogheader">
                <h1>Nuestro catálogo</h1>
                <form class="searchform">
                    <label for="search">
                        <input required="" autocomplete="off" placeholder="Buscar producto" id="search" type="text">
                        <div class="icon">
                            <svg stroke-width="2" stroke="currentColor" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg" class="swap-on">
                                <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-linejoin="round"
                                    stroke-linecap="round"></path>
                            </svg>
                            <svg stroke-width="2" stroke="currentColor" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg" class="swap-off">
                                <path d="M10 19l-7-7m0 0l7-7m-7 7h18" stroke-linejoin="round" stroke-linecap="round">
                                </path>
                            </svg>
                        </div>
                        <button type="reset" class="close-btn">
                            <svg viewBox="0 0 20 20" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg">
                                <path clip-rule="evenodd"
                                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                    fill-rule="evenodd"></path>
                            </svg>
                        </button>
                    </label>
                </form>
            </header>
            <section class="productsvisualizer">
                <?php for($i=0; $i<10; $i++) { echo RequireProduct(); } ?>
            </section>
        </main>
    </div>

    <script src="menu.js"></script>
</body>

</html>
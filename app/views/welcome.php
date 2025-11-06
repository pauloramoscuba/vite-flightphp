<?php

/**
* @var object $app
* @var string $message
*/

?>
<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vite App</title>
    <?= $app->vite()->entry('main.js'); ?>

</head>

<body class="bg-[#cdd6f4] dark:bg-[#1e1e2e]">

    <header id="page-header" class="fixed inset-x-0 top-0 z-30 flex flex-none items-center border-b border-white bg-[#cdd6f4] backdrop-blur-sm dark:border-black dark:bg-[#1e1e2e] h-18">
        <div class="container mx-auto xl:max-w-7xl px-4 lg:px-8 flex grow items-center justify-between gap-2">
            <div class="flex items-center gap-3">
                <button type="button" class="hs-dark-mode-active:hidden block hs-dark-mode font-medium text-gray-800 rounded-full hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200" data-hs-theme-click-value="dark">
                    <span class="group inline-flex shrink-0 justify-center items-center size-9">
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"></path></svg>
                    </span>
                </button>
                <button type="button" class="hs-dark-mode-active:block hidden hs-dark-mode font-medium text-white rounded-full hover:bg-gray-800 focus:outline-hidden focus:bg-gray-800" data-hs-theme-click-value="light">
                    <span class="group inline-flex shrink-0 justify-center items-center size-9">
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="4"></circle>
                            <path d="M12 2v2"></path>
                            <path d="M12 20v2"></path>
                            <path d="m4.93 4.93 1.41 1.41"></path>
                            <path d="m17.66 17.66 1.41 1.41"></path>
                            <path d="M2 12h2"></path>
                            <path d="M20 12h2"></path>
                            <path d="m6.34 17.66-1.41 1.41"></path>
                            <path d="m19.07 4.93-1.41 1.41"></path>
                        </svg>
                    </span>
                </button>
            </div>
        </div>
    </header>

    <div class="grid place-items-center px-4 mt-24">

        <h1 class="text-3xl font-bold dark:text-[#cdd6f4]">Welcome to the FlightPHP Skeleton Example!</h1>
        <?php if (!empty($message)) { ?>
        <h3 class="text-xl font-semibold dark:text-[#cdd6f4]"><?=$message?></h3>
        <br>
        <?php } ?>

        <div id="app" class="py-4"></div>

    </div>

</body>

</html>

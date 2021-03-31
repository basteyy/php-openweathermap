<?php

// Include the Class
require dirname(__DIR__) . '/src/OpenWeatherMap.php';

// Setup your API Key
$api_key = 'abc';

// Define a cache folder
$cache_folder = __DIR__ . DIRECTORY_SEPARATOR . 'cache';

// Define the language
$api_lang = 'en';

// Construct the class
$openweathermap = new \basteyy\PhpOpenWeatherMap\OpenWeatherMap($api_key, $cache_folder, $api_lang);

// Create Cache Folder
if (!is_dir($cache_folder)) {
    mkdir($cache_folder);

    if (!is_dir($cache_folder)) {
        throw new \Exception('Unable to create cache folder.');
    }
}

// Request the data
foreach (['Berlin', 'Paris', 'London'] as $city) {

    printf('Temperature in %1$s is %2$s and the weather is %3$s <img src="%4$s" title="The Weather in %1$s"" />',
        $city,
        $openweathermap->getTemperature($city),
        $openweathermap->getWeather($city),
        $openweathermap->getIcon($city)
    );

    echo '<hr />';
}

// Delete all Cache Files
$openweathermap->clearCache();
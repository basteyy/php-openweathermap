<?php

namespace basteyy\PhpOpenWeatherMap;

use Exception;

/**
 * Class OpenWeatherMap
 * @package basteyy\PhpOpenWeatherMap
 */
class OpenWeatherMap
{
    /**
     * @var string Url of the api
     */
    private string $apiUrl = 'https://api.openweathermap.org/data/2.5/weather?q=%1$s&APPID=%2$s&units=%3$s&lang=%4$s';
    /**
     * @var array Storage for cached results from current scope
     */
    private array $cachedResults = [];
    /**
     * @var string The api key
     */
    private string $apiKey;
    /**
     * @var string Path where the cached files are stored
     */
    private string $cacheLocation;
    /**
     * @var int Cachetime before re-request data
     */
    private int $cacheTime;
    /**
     * @var string Format for the units
     */
    private string $unitFormat;
    /**
     * @var string URL for wheather icons
     */
    private string $iconUrl = 'https://openweathermap.org/img/w/%s.png';
    /**
     * @var string Local filename for the icon
     */
    private string $iconLocalFilename = '%s.png';
    /**
     * @var string Appended information for inlinde data
     */
    private string $icoSrcAppendix = 'data:image/png;base64, ';
    /**
     * @var string The language for the request
     */
    private string $lang = 'en';
    /**
     * @var array|string[] Supported languges
     */
    private array $supportedLang = ['af', 'al', 'ar', 'az', 'bg', 'ca', 'cz', 'da', 'de', 'el', 'en', 'eu', 'fa', 'fi', 'fr', 'gl', 'he', 'hi', 'hr', 'hu', 'id', 'it', 'ja', 'kr', 'la', 'lt', 'mk', 'no', 'nl', 'pl', 'pt', 'pt_br', 'ro', 'ru', 'sv', 'se', 'sk', 'sl', 'sp', 'es', 'sr', 'th', 'tr', 'ua', 'uk', 'vi', 'zh_cn', 'zh_tw', 'zu'];

    /**
     * OpenWeatherMap constructor.
     * @param string $apiKey
     * @param string $cacheLocation
     * @param string $lang
     * @param int $cacheTime
     * @param bool $metric
     * @throws Exception
     */
    public function __construct(string $apiKey, string $cacheLocation, string $lang = 'en', int $cacheTime = 3600, bool $metric = true)
    {
        if (strlen($apiKey) !== 32) {
            throw new Exception('Invalid key');
        }
        $this->apiKey = $apiKey;

        $cacheLocation = substr($cacheLocation, -1, 1) != DIRECTORY_SEPARATOR ? $cacheLocation . DIRECTORY_SEPARATOR : $cacheLocation;

        if (!is_dir($cacheLocation)) {
            throw new Exception(sprintf('Cache Location is required to set, exists and be writable. Given location: %s', $cacheLocation));
        }
        $this->cacheLocation = $cacheLocation;

        if ($cacheTime < 0) {
            throw new Exception('Cache Time must be higher than 0. Use 0 for deactivate caching');
        }
        $this->cacheTime = $cacheTime;

        $this->unitFormat = $metric ? 'metric' : 'imperial';

        if (!in_array($lang, $this->supportedLang)) {
            throw new Exception(sprintf('Language %s is not supported.', $lang));
        }

        $this->lang = $lang;
    }

    /**
     * Method returns data from cache or from performed request
     * @param string $locationName
     * @return array
     * @throws Exception
     */
    private function getData(string $locationName): array
    {

        $hashedLocationName = md5($locationName);

        if (isset($this->cachedResults[$hashedLocationName])) {
            return $this->cachedResults[$hashedLocationName];
        }

        $cacheFile = $this->cacheLocation . DIRECTORY_SEPARATOR . $hashedLocationName . '.' .$this->lang . '.json';

        if ($this->cacheTime === 0 || !file_exists($cacheFile) || (filemtime($cacheFile) + $this->cacheTime) < time()) {
            $data = $this->performApiRequest($locationName);

            if ($this->cacheTime > 0) {
                $this->putDataToFile($cacheFile, $data);
            }
        } else {
            $data = $this->getDataFromFile($cacheFile);
        }

        $this->cachedResults[$hashedLocationName] = $data;

        return $this->cachedResults[$hashedLocationName];
    }

    /**
     * The request for grapping the data
     * @param string $location
     * @return array
     * @throws Exception
     */
    private function performApiRequest(string $location): array
    {
        $result = file_get_contents(sprintf( $this->apiUrl,
            $location,
            $this->apiKey,
            $this->unitFormat,
            $this->lang
        ));

        if (!$result) {
            throw new Exception('Cannot access api');
        }

        return json_decode($result, true);
    }

    /**
     * Save data to file
     * @param string $filepath
     * @param array $data
     * @throws Exception
     */
    private function putDataToFile(string $filepath, array $data): void
    {
        if (!is_dir(dirname($filepath))) {
            throw new Exception(sprintf('Folder %s not exists', dirname($filepath)));
        }

        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Get data from file
     * @param string $filepath
     * @return array
     * @throws Exception
     */
    private function getDataFromFile(string $filepath): array
    {
        if (!file_exists($filepath)) {
            throw new Exception(sprintf('File %s not found', $filepath));
        }

        return json_decode(file_get_contents($filepath), true);
    }

    /**
     * Return data
     *
     * @param $name
     * @param $locationName
     * @return string
     * @throws Exception
     */
    public function __call($name, $locationName) : string
    {
        if(count($locationName) > 1) {
            throw new Exception('Not supported arguments!');
        }

        $data = $this->getData($locationName[0]);

        switch ($name) {

            case 'getWeather':
                $requestedData = $data['weather'][0]['description'];
                break;

            case 'getFeelsLike':
                $requestedData = $data['main']['feels_like'];
                break;

            case 'getTemperature':
                $requestedData = $data['main']['temp'];
                break;

            case 'getIcon':
                $localFilename = $this->cacheLocation . sprintf($this->iconLocalFilename, $data['weather'][0]['icon']);

                if (!file_exists($localFilename)) {
                    file_put_contents($localFilename, file_get_contents(sprintf($this->iconUrl, $data['weather'][0]['icon'])));
                }

                $requestedData = $this->icoSrcAppendix . base64_encode(file_get_contents($localFilename));
                break;

            default:
                throw new \Exception('Unknown data requested.');

        }

        return $requestedData;
    }

    /**
     * Clear the Cache from json and png
     */
    public function clearCache() : void {
        foreach (glob($this->cacheLocation . '*.json') as $file) {
            unlink($file);
        }
        foreach (glob($this->cacheLocation . '*.png') as $file) {
            unlink($file);
        }
    }

}
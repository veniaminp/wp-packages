<?php
/**
 * WebProduction Packages
 * Copyright (C) 2007-2011 WebProduction <webproduction.com.ua>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU LESSER GENERAL PUBLIC LICENSE
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

/**
 * Загрузчик WebProduction Packages.
 * Позволяет подключать к проектам PHP-классы,
 * директории с PHP-классами, CSS-файлы, CSS-данные,
 * JS-файлы, JS-данные, умеет компилировать последние четыре
 * в виде отдельных файлов.
 *
 * @author Maxim Miroshnichenko <max@webproduction.com.ua>
 * @copyright WebProduction
 * @package PackageLoader
 */
class PackageLoader {

    /**
     * Допускается ли режим autoload классов?
     *
     * @var string
     */
    private $_autoload;

    private function __construct() {
        if (function_exists('__autoload')) {
            // так как есть ранее определенная __autoload,
            // то регистрировать свою мы не можем
            $this->_autoload = false;
        } else {
            spl_autoload_register(array($this, 'loadClass'));
            $this->_autoload = true;
        }

        // по умолчанию packagePath на один уровень выше самого пакета PackageLoader
        $this->setPackagesPath(dirname(__FILE__).'/../');

        // если есть PROJECT_PATH, то по дефолту устанавливаем
        // путь к проекту
        // @deprecated: @todo: в будущем удалить
        if (defined('PROJECT_PATH')) {
            $this->setProjectPath(PROJECT_PATH);
        }
    }

    private $_packagePath;

    /**
     * Установить путь к пакетам. Путь можно менять во время работы.
     *
     * @param string $path
     */
    public function setPackagesPath($path) {
        if (!file_exists($path) || !is_dir($path)) {
            throw new PackageLoader_Exception("Path '$path' not found");
        }

        $this->_packagePath = $path;
    }

    private $_loadClassArray = array();

    /**
     * Подключить класс (загрузить его).
     * Этот метод вызывается через spl_autoload_register()
     *
     * @param string $className
     */
    public function loadClass($className) {
        if (!empty($this->_files['php'][$className])) {
            $file = $this->_files['php'][$className];

            $t = microtime(true);
            include_once($file);
            $t = microtime(true) - $t;

            // записываем статистику
            $this->_loadClassArray[] = array(
            'class' => $className,
            'time' => number_format($t, 8),
            'path' => $file,
            );
        }
    }

    /**
     * Получить массив загруженных классов
     *
     * @return array
     */
    public function getLoadedClasses() {
        $a = array();
        foreach ($this->_loadClassArray as $c) {
            $a[] = $c['class'];
        }
        return $a;
    }

    /**
     * Получить статистику по загруженным классам
     *
     * @return array
     */
    public function getLoadedClassesStatistics() {
        return $this->_loadClassArray;
    }

    private static $_Instance = null;

    /**
     * @return PackageLoader
     */
    public static function Get() {
        if (!self::$_Instance) {
            self::$_Instance = new self();
        }
        return self::$_Instance;
    }

    private $_files = array();

    /**
     * Зарегистрировать PHP-класс, чтобы PackageLoader
     * мог его загружать
     *
     * @param string $file
     */
    public function registerPHPClass($file) {
        $file = str_replace('//', '/', $file);

        if (!file_exists($file) || !is_file($file)) {
            throw new PackageLoader_Exception("File '{$file}' not found");
        }

        $hash = basename($file);
        $hash = str_replace('.class.php', '', $hash);
        $hash = str_replace('.interface.php', '', $hash);
        $hash = str_replace('.php', '', $hash);
        $this->_files['php'][$hash] = $file;

        if (!$this->_autoload) {
            include_once($file);
        }
    }

    /**
     * Зарегистрировать директорию с php-классами.
     * Не рекомендуется к использованию, так как порядок
     * подключения может быть абсолютно рандомный.
     *
     * Так как не соблюдается порядок подключения, рекомендуется
     * использовать registerPHPClass()
     * @see registerPHPClass()
     *
     * @param string $dir
     */
    public function registerPHPDirectory($dir) {
        if (!file_exists($dir) || !is_dir($dir)) {
            throw new PackageLoader_Exception("Directory {$dir} not found");
        }

        // @todo: заменить на scandir?
        $d = opendir($dir);
        while ($x = readdir($d)) {
            if (preg_match("/^(.*?)\.class\.php$/is", $x)
            || preg_match("/^(.*?)\.interface\.php$/is", $x)) {
                $this->registerPHPClass($dir.'/'.$x);
            }
        }
        closedir($d);
    }

    /**
     * Зарегистрировать JS-файл
     *
     * @param string $file
     */
    public function registerJSFile($file, $absolutePath = false) {
        $this->_registerFile('js', $file, $absolutePath);
    }

    /**
     * Зарегистрировать CSS-файл
     *
     * @param string $file
     */
    public function registerCSSFile($file, $absolutePath = false) {
        $this->_registerFile('css', $file, $absolutePath);
    }

    /**
     * Получить все css-файлы, которые нужно подключить
     *
     * @return array
     */
    public function getCSSFiles() {
        if (!empty($this->_files['css'])) {
            return $this->_files['css'];
        }
        return array();
    }

    /**
     * Получить все js-файлы, которые нужно подключить
     *
     * @return array
     */
    public function getJSFiles() {
        if (!empty($this->_files['js'])) {
            return $this->_files['js'];
        }
        return array();
    }

    /**
     * Регистрируем файл
     *
     * @param string $type
     * @param string $file
     * @param bool $absolutePath
     */
    private function _registerFile($type, $file, $absolutePath = false) {
        if ($absolutePath) {
            $file = str_replace('//', '/', $file);

            // только для абсолютных путей выполняем проверку на наличие файла
            if (!file_exists($file) || !is_file($file)) {
                throw new PackageLoader_Exception("Path '{$file}' not found");
            }
        }

        if ($absolutePath) {
            $file = str_replace(PROJECT_PATH, '/', str_replace('\\', '/', $file));
        }

        $hash = crc32(basename($file));
        $this->_files[$type][$hash] = $file;
    }

    /**
     * Подключить пакет.
     * В $package можно передать имя пакета
     * или абсолютный путь к директории пакета
     * или абсолютный путь к include.php в директории пакета
     *
     * @throws PackageLoader_Exception
     * @param string $package
     * @param mixed $paramsArray
     * @return bool
     */
    public function import($package, $paramsArray = array()) {
        $t = microtime(true);

        // по параметру $package делаем разбивку на "имя пакета" и "путь к пакету"
        if (is_dir($package)) {
            // указан путь к директории (явно)
            $x = pathinfo($package);
            $packageDirectory = $package;
            $packageName = @$x['filename'];
        } elseif (is_file($package)) {
            // указан путь к файлу include.php
            $x = pathinfo($package);
            $packageDirectory = @$x['dirname'];
            $packageName = basename($packageDirectory);
        } else {
            // указано просто имя пакета
            $packageName = $package;
            $packageDirectory = $this->_packagePath.'/'.$packageName;
        }

        // проверяем, подключен ли пакет
        if ($this->isImported($packageName)) {
            // throw new PackageLoader_Exception("Package '{$package}' already imported!");
            return false;
        }

        if (!is_array($paramsArray)) {
            $paramsArray = array($paramsArray);
        }

        $packageInclude = $packageDirectory.'/include.php';

        // подключаем include-файл пакета
        if (!file_exists($packageInclude)) {
            throw new PackageLoader_Exception("Package '{$packageName}' not found at path '{$packageInclude}'");
        }
        include_once($packageInclude);

        // пытаемся найти Loader
        $classname = $packageName.'_Loader';
        if (class_exists($classname)) {
            // если есть Loader пакета - вызываем его
            $obj = new $classname($paramsArray);
        }

        // запоминаем, какие пакеты подключены
        if (!isset($this->_importedArray[$packageName])) {
            $this->_importedArray[$packageName] = array(
            'package' => $packageName,
            'time' => number_format(microtime(true) - $t, 8),
            'path' => $packageInclude,
            );
        }

        // мы только что подключили пакет - true
        return true;
    }

    /**
     * Список пакетов, которые уже подключены
     *
     * @var array
     */
    private $_importedArray = array();

    /**
     * Реестр данных
     *
     * @var array
     */
    private $_data = array('css' => '', 'js' => '');

    /**
     * Зарегистрировать CSS-данные
     *
     * @param string $data
     * @param bool $compile Разрешить скомпилировать в файл?
     */
    public function registerCSSData($data, $compile = false) {
        $this->_registerData('css', $data, $compile);
    }

    /**
     * Зарегистрировать JavaScript-данные
     *
     * @param string $data
     * @param bool $compile Разрешить скомпилировать в файл?
     */
    public function registerJSData($data, $compile = false) {
        $this->_registerData('js', $data, $compile);
    }

    /**
     * Зарегистрировать css/js данные
     *
     * @param string $type
     * @param string $data
     * @param bool $compile Компилировать в файл?
     */
    private function _registerData($type, $data, $compile) {
        $data = trim($data);
        $data = str_replace('  ', ' ', $data);

        if (!$data) {
            throw new PackageLoader_Exception('No '.$type.' data to register');
        }

        // строим хеш данных
        $hash = sha1($data);
        if (isset($this->_dataHash[$type.$hash])) {
            // если такие данные уже зарегистрированы - то сразу выходим
            return false;
        }

        // записываем хеш в реестр
        $this->_dataHash[$type.$hash] = true;

        // запускаем препроцессоры,
        // которые могут, например, упаковать CSS
        $dataProcessors = @$this->_dataProcessors[$type];
        if ($dataProcessors) {
            foreach ($dataProcessors as $processor) {
                $data = $processor->processBefore($data);
            }
        }

        if ($compile) {
            // регистрируем данные как компилированный файл
            $pathCompile = dirname(__FILE__).'/compile/';
            $pathFile = $pathCompile.$hash.'.'.$type;
            if (!file_exists($pathFile)) {
                file_put_contents($pathFile, $data, LOCK_EX);
            }

            // регистрируем как файл
            $this->_registerFile($type, $pathFile, true);
        } else {
            // регистрируем данные в памяти
            // (потом их будут просить у PackageLoader)
            $this->_data[$type] .= "\n\n".$data;
        }
    }

    /**
     * Массив хешей для данных.
     * Таким образом отсекается регистрация таких-же данных
     * при вызове registerCSS/JSData()
     *
     * @var array
     */
    private $_dataHash = array();

    /**
     * Получить зарегистрированные CSS-данные (css-код)
     *
     * @return string
     */
    public function getCSSData() {
        return $this->_getData('css');
    }

    /**
     * Получить зарегистрированные JavaScript-данные (js-код)
     *
     * @return string
     */
    public function getJSData() {
        return $this->_getData('js');
    }

    private function _getData($type) {
        $data = @trim($this->_data[$type]);

        $dataProcessors = @$this->_dataProcessors[$type];
        if ($dataProcessors) {
            foreach ($dataProcessors as $processor) {
                $data = $processor->processAfter($data);
            }
        }

        return $data;
    }

    /**
     * Проверить, подключен ли пакет
     *
     * @param string $packageName
     * @return bool
     */
    public function isImported($packageName) {
        return (isset($this->_importedArray[$packageName]));
    }

    /**
     * Получить массив зарегистрированных пакетов
     *
     * @return array
     */
    public function getImportedPackages() {
        $a = array();
        foreach ($this->_importedArray as $p) {
            $a[] = $p['package'];
        }
        return $a;
    }

    /**
     * Получить статистику по зарегистрированным пакетам
     *
     * @return array
     */
    public function getImportedPackagesStatistics() {
        return $this->_importedArray;
    }

    public function registerCSSDataProcessor(PackageLoader_IDataProcessor $processor) {
        $this->registerDataProcessor($processor, 'css');
    }

    public function registerJSDataProcessor(PackageLoader_IDataProcessor $processor) {
        $this->registerDataProcessor($processor, 'js');
    }

    public function registerDataProcessor(PackageLoader_IDataProcessor $processor, $type) {
        $type = trim($type);
        $type = strtolower($type);
        $typesArray = array('css', 'js');
        if (!in_array($type, $typesArray)) {
            throw new PackageLoader_Exception("Unknown DataProcessor type '{$type}'");
        }

        $this->_dataProcessors[$type][] = $processor;
    }

    private $_dataProcessors = array();

    private $_modeArray = array();

    /**
     * Установить режим
     *
     * @param string $mode
     * @param bool $value
     */
    public function setMode($mode, $value = true) {
        if (!$mode) {
            throw new PackageLoader_Exception("Empty mode value");
        }
        $this->_modeArray[$mode] = $value;
    }

    /**
     * Узнать состояние режима $mode
     *
     * @param string $mode
     * @return bool
     */
    public function getMode($mode) {
        if (!$mode) {
            throw new PackageLoader_Exception("Empty mode value");
        }
        return !empty($this->_modeArray[$mode]);
    }

    private $_projectPath;

    /**
     * Получить полный путь к проекту.
     * Метод рекоммендуется использовать вместо константы PROJECT_PATH
     *
     * @return string
     */
    public function getProjectPath() {
        if (!$this->_projectPath) {
            throw new PackageLoader_Exception("Project path not defined");
        }
        return $this->_projectPath;
    }

    /**
     * Задать полный путь к проекту
     *
     * @param string $path
     */
    public function setProjectPath($path) {
        $path = str_replace('\\', '/', $path);
        if (!is_dir($path)) {
            throw new PackageLoader_Exception("Incorrect project path '{$path}'");
        }
        $path = str_replace('//', '/', $path.'/');
        $this->_projectPath = $path;
    }

}
<?php

namespace PhpStarterKit;

use ReflectionMethod;

class Project extends Core
{
    protected array $project = [];

    public function __construct()
    {
        $this->displayErrors();
        $this->loadContent();
        $this->handleRequest();

        $this->setProtocol();
        $this->setPort();
        $this->setCanonical();
        $this->setPath();
        $this->setPage();
        $this->setView();

        define('PROJECT', $this->project);
    }

    private function displayErrors(): void
    {
        error_reporting(E_ALL);

        if(ENV['debug']) {
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
        }
        else {
            ini_set('display_errors', 0);
            ini_set('display_startup_errors', 0);
        }
    }

    private function loadContent(): void
    {
        if(!is_dir(PATHS['resources'] . '/contents/' . ENV['lang'])) {
            exit('Content directory ' . strtoupper(ENV['lang']) . ' not exist!');
        }

        $contentDir = PATHS['resources'] . '/contents/' . ENV['lang'];

        foreach(scandir($contentDir) as $file) {
            if(in_array($file, ['.', '..'])) continue;

            $basename = basename($file, '.php');

            $this->project['contents'][$basename] = require_once($contentDir . '/' . $file);

            define(strtoupper($basename), $this->project['contents'][$basename]);
        }
    }

    private function handleRequest(): void
    {
        $request = json_decode(file_get_contents('php://input'), TRUE);
        $callable = 0;

        if(isset($request['module']) && isset($request['action'])) {
            [$class, $method, $data] = [
                $request['module'],
                $request['action'],
                $request['data'] ?? ''
            ];

            if(method_exists($class, $method)) {
                $reflection = new ReflectionMethod($class, $method);

                if($reflection->isPublic()) {
                    $callable = 1;
                }
            }

            if(!in_array($method, $class::$ajax ?? [])) {
                $response = [
                    'type'    => 'error',
                    'message' => self::__('messages.request.response.error.ajax'),
                ];

                exit(json_encode($response));
            }

            if($callable) {
                $result = $class::$method($data);

                if(is_array($result)) {
                    $response = [
                        'type'    => 'success',
                        'message' => $result,
                    ];
                }
                elseif($result) {
                    $response = [
                        'type'    => 'success',
                        'message' => self::__('messages.request.response.success'),
                    ];
                }
                else {
                    $response = [
                        'type'    => 'error',
                        'message' => self::__('messages.request.response.error.incorrect'),
                    ];
                }
            }
            else {
                $response = [
                    'type'    => 'error',
                    'message' => self::__('messages.request.response.error.failed'),
                ];
            }

            exit(json_encode($response));
        }
    }

    private function loadView()
    {
        $sourceViewsDir = PATHS['resources'] . '/views';
        $cacheViewsDir = PATHS['storage'] . '/cache/views';

        $templateType = 'html';
        $templateSourcePath = $sourceViewsDir . '/' . $this->project['view'] . '.' . $templateType;
        $templateCachePath = $cacheViewsDir . '/' . $this->project['view'] . '.php';

        if(!is_file($templateSourcePath)) {
            exit('View ' . $this->project['view'] . '.' . $templateType . ' not found!');
        }

        if(@filemtime($templateSourcePath) > @filemtime($templateCachePath) || @filemtime($templateCachePath) < time() - 3600 * 24 || ENV['debug']) {
            $templateCode = file_get_contents($templateSourcePath);
            $include = 1;

            while($include) {
                preg_match_all('/@include\((.*)\)+/', $templateCode, $results);

                if(count($results[0])) {
                    foreach($results[0] as $result) {
                        $partial = str_replace('@include', '', $result);
                        $partial = str_replace('.', '/', trim($partial, '()"\\\''));

                        if(is_file($sourceViewsDir . '/' . $partial . '.' . $templateType)) {
                            $templateCode = str_replace($result, file_get_contents($sourceViewsDir . '/' . $partial . '.' . $templateType), $templateCode);
                        }
                    }
                }
                else {
                    $include = 0;
                }
            }

            preg_match_all('/{{ (.*?)\((.*?)\) }}/', $templateCode, $results);

            foreach($results[0] as $key => $result) {
                $pattern = '<?php echo Page::run(\'' . $results[1][$key] . '\', ' . $results[2][$key] . ') ?>';
                $templateCode = str_replace($results[0][$key], $pattern, $templateCode);
            }

            preg_match_all('/{{ (.*?) }}/', $templateCode, $results);

            foreach($results[0] as $result) {
                $pattern = str_replace(['{{', '}}'], ['<?php echo', '?>'], $result);
                $templateCode = str_replace($result, $pattern, $templateCode);
            }

            preg_match_all('/@if\((.*)\)+/', $templateCode, $results);

            foreach($results[0] as $key=>$result) {
                $templateCode = str_replace($result, '<?php if(' . $results[1][$key] . '): ?>', $templateCode);
            }

            preg_match_all('/@elseif\((.*)\)+/', $templateCode, $results);

            foreach($results[0] as $key=>$result) {
                $templateCode = str_replace($result, '<?php elseif(' . $results[1][$key] . '): ?>', $templateCode);
            }

            preg_match_all('/@else/', $templateCode, $results);

            foreach($results[0] as $result) {
                $templateCode = str_replace($result, '<?php else: ?>', $templateCode);
            }

            preg_match_all('/@endif/', $templateCode, $results);

            foreach($results[0] as $result) {
                $templateCode = str_replace($result, '<?php endif ?>', $templateCode);
            }

            preg_match_all('/@isset\((.*)\)+/', $templateCode, $results);

            foreach($results[0] as $key=>$result) {
                $templateCode = str_replace($result, '<?php if(isset(' . $results[1][$key] . ')): ?>', $templateCode);
            }

            preg_match_all('/@endisset/', $templateCode, $results);

            foreach($results[0] as $result) {
                $templateCode = str_replace($result, '<?php endif ?>', $templateCode);
            }

            preg_match_all('/@foreach\((.*?) as (.*?)\)/', $templateCode, $results);

            foreach($results[0] as $key => $result) {
                $pattern = '<?php foreach(' . $results[1][$key] . ' as ' . $results[2][$key] . '): ?>';
                $templateCode = str_replace($result, $pattern, $templateCode);
            }

            preg_match_all('/@endforeach/', $templateCode, $results);

            foreach($results[0] as $result) {
                $templateCode = str_replace($result, '<?php endforeach ?>', $templateCode);
            }

            if(!is_dir($cacheViewsDir)) {
                mkdir($cacheViewsDir, 0755, TRUE);
            }

            file_put_contents($templateCachePath, $templateCode);
        }

        return require_once($templateCachePath);
    }

    private function setProtocol(): void
    {
        $protocol = 'http';

        if((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || $_SERVER['SERVER_PORT'] == 443) {
            $protocol = 'https';
        }

        $this->project['protocol'] = $protocol;
    }

    private function setPort(): void
    {
        $this->project['port'] = $_SERVER['SERVER_PORT'] ?? '';
    }

    private function setCanonical(): void
    {
        $port = in_array($this->project['port'], [80, 443]) ? '' : $this->project['port'];
        $port = $port ? ':' . $port : '';

        $canonical = $this->project['protocol'] . '://' . $_SERVER['SERVER_NAME'] . $port . rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

        $this->project['canonical'] = $canonical;
    }

    private function setPath(): void
    {
        if(!isset(ENV['url'])) {
            exit('Variable URL not set in .env!');
        }

        $path = explode(ENV['url'], $this->project['canonical']);
        $path = (count($path) > 1) ? trim($path[1], '/') : '';

        $this->project['path'] = trim($path);
    }

    private function setPage(): void
    {
        $this->project['page'] = $this->project['path'] ?: 'index';

        if($this->project['path'] == 'index') {
            header('Location: ' . ENV['url']);
        }
    }

    private function setView(): void
    {
        if(isset($this->project['contents']['pages'][$this->project['page']])) {
            $this->project['view'] = $this->project['contents']['pages'][$this->project['page']]['view'] ?? $this->project['page'];
        }
        else if(isset($this->project['contents']['pages']['404'])) {
            header('Location: ' . ENV['url'] . '/404');
        }
    }

    public function display(): void
    {
        $this->loadView();
    }
}
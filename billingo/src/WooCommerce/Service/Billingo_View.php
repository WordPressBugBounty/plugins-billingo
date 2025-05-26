<?php

namespace App\Billingo\WooCommerce\Service;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

class Billingo_View
{
    private static ?Billingo_View $instance = null;
    private Environment $twig;
    private string $viewPath;

    private function __construct()
    {
        $this->viewPath = __DIR__ . '/../Views';
        $loader = new FilesystemLoader($this->viewPath);
        $this->twig = new Environment($loader);

        $this->twig->addFilter(new TwigFilter('translate', function ($string) {

            return __($string, 'billingo');
        }));

        $this->twig->addFilter(new TwigFilter('esc_html_translate', function ($string) {

            return esc_html__($string, 'billingo');
        }));

        $this->twig->addFilter(new TwigFilter('esc_attr', function ($string) {

            return esc_attr($string);
        }));

        $this->twig->addFunction(new TwigFunction('__', function ($string) {
            return __($string, 'billingo');
        }));
        
        $this->twig->addFunction(new TwigFunction('esc_html__', function ($string) {
            return esc_html__($string, 'billingo');
        }));

        $this->twig->addFunction(new TwigFunction('selected', function ($selected, $current) {

            return selected($selected, $current, false);
        }));

        $this->twig->addFunction(new TwigFunction('checked', function ($checked, $current = true) {

            return checked($checked, $current, false);
        }));

        $this->twig->addFunction(new TwigFunction('file_get_contents', function ($filepath) {
            if (file_exists($filepath)) {

                return file_get_contents($filepath);
            } else {

                return 'File not found';
            }
        }));

        $this->twig->addFunction(new TwigFunction('esc_url', function ($string) {

            return esc_url($string);
        }));

        $this->twig->addFunction(new TwigFunction('esc_url_raw', function ($string) {

            return esc_url_raw($string);
        }, ['is_safe' => ['html']]));
    }

    public static function getInstance(): Billingo_View
    {
        if (self::$instance === null) {
            self::$instance = new Billingo_View();
        }

        return self::$instance;
    }

    /**
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function getViewContent(string $identifier, array $variables): string
    {
        $templateFile = str_replace('.', '/', $identifier) . '.twig';

        if ($this->twig->getLoader()->exists($templateFile)) {
            return $this->twig->render($templateFile, $variables);
        }

        return 'File not found';
    }
}

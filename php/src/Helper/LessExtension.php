<?php
namespace Helper;

use Silex\Application;
use Symfony\Component\PropertyAccess\PropertyAccess;

class LessExtension extends \Twig_Extension
{
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'less';
    }

    public function getFunctions()
    {
        return array(
            'less' => new \Twig_Function_Method($this, 'renderLess',
                                                array('is_safe' => array('html'))),
        );
    }

    /**
     * Renders a less-stylesheet include.
     *
     * @param string              $less_filename
     * @param array               $options    An array of options (optional).
     *
     * @return string The link/script tags
     */
    public function renderLess($less_filename, array $options = array())
    {
        // $options = array_replace($this->app['less.view.options'], $options);

        $base_path = $this->app['request']->getBasePath();
        $base_dir = dirname($_SERVER['SCRIPT_FILENAME']);
        if (file_exists($base_dir . $less_filename)) {
            // try to compile
            $less = new \lessc;
            try {
                $less_out = preg_replace('/\.less/', '.css', $less_filename);
                $less->checkedCompile($base_dir . $less_filename,
                                      $base_dir . $less_out);

                $href_css = htmlspecialchars($base_path . $less_out);
        return <<<EOT
    <!-- compiled less -->
    <link rel="stylesheet" type="text/css" href="{$href_css}" />
EOT;
            }
            catch (\Exception $e) {
            }
        }
        $href_less = htmlspecialchars($base_path . $less_filename);
        $src_js = htmlspecialchars($base_path .  '/js/less-1.7.0.min.js');

        return <<<EOT
    <!-- less -->
    <link rel="stylesheet/less" type="text/css" href="{$href_less}" />
    <script src="{$src_js}"></script>
EOT;

    }

}

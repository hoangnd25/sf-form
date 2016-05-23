<?php

namespace HND\SymfonyForm\Loader;

use TwigBridge\Twig\Loader as BaseLoader;

class Loader extends BaseLoader
{
    /**
     * Normalize the Twig template name to a name the ViewFinder can use
     *
     * @param  string $name Template file name.
     * @return string The parsed name
     */
    protected function normalizeName($name)
    {
        if ($this->files->extension($name) === $this->extension) {
            $name = substr($name, 0, - (strlen($this->extension) + 1));

            if($this->files->extension($name) === 'html') {
                $name = substr($name, 0, - 5);
            }
        }

        return $name;
    }

}

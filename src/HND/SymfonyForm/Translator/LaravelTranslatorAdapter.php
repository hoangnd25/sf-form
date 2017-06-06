<?php

namespace HND\SymfonyForm\Translator;

use Illuminate\Contracts\Translation\Translator;
use Symfony\Component\Translation\TranslatorInterface;

class LaravelTranslatorAdapter implements TranslatorInterface
{
    protected $translator;

    /**
     * LaravelTranslatorAdapter constructor.
     * @param Translator $translator
     */
    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    public function trans($id, array $parameters = array(), $domain = null, $locale = null){
        return $this->translator->trans($id, $parameters, 'en');
    }

    public function transChoice($id, $number, array $parameters = array(), $domain = null, $locale = null){
        return $this->translator->transChoice($id, $number, $parameters, $locale);
    }

    public function setLocale($locale){
        $this->translator->setLocale($locale);
    }

    public function getLocale(){
        return $this->translator->getLocale();
    }
}
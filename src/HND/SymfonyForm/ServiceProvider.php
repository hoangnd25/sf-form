<?php

namespace HND\SymfonyForm;

use HND\SymfonyForm\CsrfToken\SessionTokenStorage;
use HND\SymfonyForm\Translator\LaravelTranslatorAdapter;
use HND\SymfonyForm\Validator\ConstraintValidatorFactory;

use Illuminate\Session\Store;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Validator\Mapping\Loader\LoaderChain;
use Symfony\Component\Validator\Mapping\Factory\LazyLoadingMetadataFactory;
use Symfony\Component\Validator\Mapping\Loader\StaticMethodLoader;
use Symfony\Component\Validator\Validation;

use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Security\Csrf\TokenStorage\NativeSessionTokenStorage;

use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension as FormValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\ResolvedFormTypeFactory;

use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Bridge\Twig\Form\TwigRenderer;

use Symfony\Bridge\Doctrine\Form\DoctrineOrmExtension;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntityValidator;
use Doctrine\Common\Annotations\AnnotationReader;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * @return string[]
     */
    public function provides()
    {
        return ['sf-form'];
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../../config/config.php' => config_path('sf-form.php')
        ], 'config');
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/config.php',
            'sf-form'
        );

        $this->registerValidator();
        $this->registerCsrf();
        $this->registerForm();
        $this->registerTwigFormExtension();

        $this->app->bind(
            'Symfony\Component\Form\FormFactoryInterface',
            $this->app['form.factory']
        );
    }

    protected function registerForm()
    {
        if (!class_exists('Locale') && !class_exists('Symfony\Component\Locale\Stub\StubLocale')) {
            throw new \RuntimeException('You must either install the PHP intl extension or the Symfony Locale Component to use the Form extension.');
        }
        if (!class_exists('Locale')) {
            $r = new \ReflectionClass('Symfony\Component\Locale\Stub\StubLocale');
            $path = dirname(dirname($r->getFilename())).'/Resources/stubs';
            require_once $path.'/functions.php';
            require_once $path.'/Collator.php';
            require_once $path.'/IntlDateFormatter.php';
            require_once $path.'/Locale.php';
            require_once $path.'/NumberFormatter.php';
        }
        $this->app['form.types'] = function ($app) {
            return array();
        };
        $this->app['form.type.extensions'] = function ($app) {
            return array();
        };
        $this->app['form.type.guessers'] = function ($app) {
            return array();
        };

        $this->app['form.extension.csrf'] = function ($app) {
            if (isset($app['translator'])) {

                if($app['translator'] instanceof TranslatorInterface) {
                    $translator = $app['translator'];
                } else {
                    $translator = new LaravelTranslatorAdapter($app['translator']);
                }

                return new CsrfExtension($app['sf.csrf.token_manager'], $translator);
            }
            return new CsrfExtension($app['sf.csrf.token_manager']);
        };

        $this->app['form.extensions'] = function ($app) {
            $extensions = array(
                new HttpFoundationExtension(),
            );

            // Csrf token integration
            if (isset($app['sf.csrf.token_manager'])) {
                $extensions[] = $app['form.extension.csrf'];
            }

            // Symfony validator integration
            if (isset($app['sf.validator'])) {
                $extensions[] = new FormValidatorExtension($app['sf.validator']);
            }

            // Doctrine integration
            if(class_exists('Symfony\\Bridge\\Doctrine\\Form\\DoctrineOrmExtension')){
                $extensions[] = new DoctrineOrmExtension($app['registry']);
            }

            return $extensions;
        };

        $this->app['form.resolved_type_factory'] = function ($app) {
            return new ResolvedFormTypeFactory();
        };

        $this->app['form.factory'] = function ($app) {
            return Forms::createFormFactoryBuilder()
                ->addExtensions($app['form.extensions'])
                ->addTypes($app['form.types'])
                ->addTypeExtensions($app['form.type.extensions'])
                ->addTypeGuessers($app['form.type.guessers'])
                ->setResolvedTypeFactory($app['form.resolved_type_factory'])
                ->getFormFactory()
                ;
        };
    }

    protected function registerTwigFormExtension()
    {
        // Load default templates from config file
        $configuration = $this->app['config']->get('sf-form');
        if(!$configuration || !$configuration['twig_templates']){
            $templates = ['bootstrap_3_layout.html.twig'];
        }else{
            $templates = $configuration['twig_templates'];
        }
        $this->app['twig.form.templates'] = $templates;

        $this->app['twig.form.engine'] = function ($app) {
            return new TwigRendererEngine($app['twig.form.templates']);
        };
        $this->app['twig.form.renderer'] = function ($app) {
            return new TwigRenderer($app['twig.form.engine'], $app['sf.csrf.token_manager']);
        };

        // Make form extension for twig available as a service
        $this->app['twig.extension.form'] = new FormExtension($this->app['twig.form.renderer']);

        $reflected = new \ReflectionClass('Symfony\Bridge\Twig\Extension\FormExtension');
        $path = dirname($reflected->getFileName()).'/../Resources/views/Form';
        $this->app['twig.loader']->addLoader(new \Twig_Loader_Filesystem($path));
        $this->app['twig.loader']->addLoader(new \Twig_Loader_Filesystem($this->app['config']['view.paths']));

        // Allow Laravel View Finder to search for twig template in Symfony Twig Bridge package
        $viewFileFinder = $this->app['view']->getFinder();
        $viewFileFinder->addLocation($path);
        $viewFileFinder->addExtension($this->app['twig.extension']);
        $viewFileFinder->addExtension('html.twig');

        $this->app->bindIf('twig.loader.viewfinder', function () {
            return new Loader\Loader(
                $this->app['files'],
                $this->app['view']->getFinder(),
                $this->app['twig.extension']
            );
        });
    }

    protected function registerCsrf()
    {
        $this->app['sf.csrf.token_manager'] = function ($app) {
            return new CsrfTokenManager($app['sf.csrf.token_generator'], $app['sf.csrf.token_storage']);
        };
        $this->app['sf.csrf.token_storage'] = function ($app) {
            // Attempt to integrate with Laravel default session
            $laravelSession = $app['session.store'];
            if($laravelSession){
                if (isset($app['session'])) {
                    if ($laravelSession instanceof SessionInterface) {
                        return new \Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage($laravelSession, $app['sf.csrf.session_namespace']);
                    } else {
                        return new SessionTokenStorage($laravelSession, $app['sf.csrf.session_namespace']);
                    }
                }
            }
            // If Laravel session is not available, use native session
            return new NativeSessionTokenStorage($app['sf.csrf.session_namespace']);
        };
        $this->app['sf.csrf.token_generator'] = function ($app) {
            return new UriSafeTokenGenerator();
        };
        $this->app['sf.csrf.session_namespace'] = '_csrf';
    }

    public function registerValidator()
    {
        $this->app['sf.validator'] = function ($app) {
            return $app['sf.validator.builder']->getValidator();
        };
        $this->app['sf.validator.builder'] = function ($app) {
            $builder = Validation::createValidatorBuilder();
            $builder->setConstraintValidatorFactory($app['sf.validator.validator_factory']);
            $builder->setTranslationDomain('sf.validators');
            $builder->addObjectInitializers($app['sf.validator.object_initializers']);
            $builder->setMetadataFactory($app['sf.validator.mapping.class_metadata_factory']);
            if (isset($app['translator'])) {

                if($app['translator'] instanceof TranslatorInterface) {
                    $translator = $app['translator'];
                } else {
                    $translator = new LaravelTranslatorAdapter($app['translator']);
                }

                $builder->setTranslator($translator);
            }
            return $builder;
        };
        $this->app['sf.validator.mapping.class_metadata_factory'] = function ($app) {
            // LoaderChain allows validator to try validate model using StaticMethodLoader first then fallback to AnnotationLoader
            // StaticMethodLoader allows validator to validate model based on metadata given in static function loadValidatorMetadata
            // AnnotationLoader allows validator to validate model annotation (e.g: @Assert\NotBlank)
            $chainLoader =  new LoaderChain([new StaticMethodLoader(), new AnnotationLoader(new AnnotationReader())]);
            return new LazyLoadingMetadataFactory($chainLoader);
        };
        $this->app['sf.validator.validator_factory'] = function ($app){
            return new ConstraintValidatorFactory($app, $app['sf.validator.validator_service_ids']);
        };
        $this->app['sf.validator.object_initializers'] = function ($app) {
            return array();
        };

        // Integrate unique entity validator (for doctrine users)
        $this->app['sf.validator.validator_service_ids'] = [
            'doctrine.orm.validator.unique' => 'validator.unique_entity'
        ];

        $this->app["validator.unique_entity"] = function ($app) {
            return isset($app['registry']) ? new UniqueEntityValidator($app["registry"]) : null;
        };
    }
}

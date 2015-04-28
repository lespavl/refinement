<?php namespace Aerynl\Refinement;

use Illuminate\Support\ServiceProvider;

class RefinementServiceProvider extends ServiceProvider {

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('aerynl/refinement');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app['refinement'] = $this->app->share(function($app){
			return new Refinement;
		});

		$this->app->booting(function(){
		  $loader = \Illuminate\Foundation\AliasLoader::getInstance();
		  $loader->alias('Refinement', 'Aerynl\Refinement\Facades\Refinement');
		});
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array('refinement');
	}

}
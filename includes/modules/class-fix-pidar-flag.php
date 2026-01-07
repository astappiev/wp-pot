<?php

namespace Pot\Modules;

use Pot\POT_Module;

defined( '\\ABSPATH' ) || exit;

class Fix_Pidar_Flag extends POT_Module {
	protected string $name = 'Fix Russian Flag';
	protected string $description = 'Replaces the flag of the Russian language with white-blue-white flag.';
	protected string $category = 'admin';
	protected array $required_plugins = [ 'polylang/polylang.php', 'polylang-pro/polylang.php' ];
	protected bool $default = true;

	public function load(): void {
		add_filter( 'pll_flag', [ $this, 'custom_flag' ], 10, 2 );
	}

	public function custom_flag( $flag, $code ) {
		if ( $code === 'ru' ) {
			$flag['src'] = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAALCAYAAAB24g05AAAACXBIWXMAAAsTAAALEwEAmpwYAAABRElEQVQokX2STU4bQRCFv+rpNjCSk0WysIJygZyBLYfhXpyBnINFpCwcywtYoWxQMhr3Tz0WY4+xCHlSV1dXlZ5etZ6t12uZGa8xv6Vj0R2AIt/3QBKx73tWqxXvobX2plZKAWC73RKHYWC3271L8D+klIjuzvdfA2MVZzGQOsMAFzQJ15SPVQzVGYtYJhibuFoW4t/cuLl7IhcnZ03L/QPS8ZiJTx8X3F4viHKnFSNnYQZd6uZPQpro9kGACUIIZAmFjhgM+hRwd6rA/FSBnYb5alnEYNj9j596vrjEJXITsTPG4gxFVIdgcJGMfhHogpFL4092vnyIfK6/iecdfPt6DkzSTpaeZRx9Umul1YqZ8fBYiXo1mHMmpUQpZTaTpDk3M1qtuDvuzjiOxObOZrPBzLBgBAu01qbhvYnMbDbPgfSAF15UvOiHoEc5AAAAAElFTkSuQmCC";
		}

		return $flag;
	}
}

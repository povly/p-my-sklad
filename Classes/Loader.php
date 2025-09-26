<?php

/**
 * Class Loader
 *
 * Registers and manages WordPress actions and filters used by the plugin.
 * This class maintains two collections:
 * - Actions: hooks triggered by specific events in WordPress.
 * - Filters: hooks that modify data before usage.
 *
 * It provides methods to add actions/filters and then register them all at once via `run()`.
 * Used as a central registry to organize hook callbacks cleanly.
 *
 * @since      1.0.0
 * @package    P_My_Sklad
 * @link       https://developer.wordpress.org/plugins/hooks/
 */

namespace P_My_Sklad;

class Loader
{

	/**
	 * The array of actions registered with WordPress.
	 *
	 * Each item contains:
	 * - hook: Name of the action.
	 * - component: Instance of the object defining the callback.
	 * - callback: Method name to call.
	 * - priority: Execution priority.
	 * - accepted_args: Number of arguments passed to the callback.
	 *
	 * @since  1.0.0
	 * @var    array
	 */
	protected $actions = [];

	/**
	 * The array of filters registered with WordPress.
	 *
	 * Structure is identical to `$actions`.
	 *
	 * @since  1.0.0
	 * @var    array
	 */
	protected $filters = [];

	/**
	 * Initialize the collection of actions and filters.
	 *
	 * @since  1.0.0
	 */
	public function __construct()
	{
		$this->actions = [];
		$this->filters = [];
	}

	/**
	 * Add a new action to the collection.
	 *
	 * @since  1.0.0
	 * @param  string   $hook            The name of the WordPress action.
	 * @param  object   $component       The object on which the callback method is defined.
	 * @param  string   $callback        The name of the method on $component.
	 * @param  int      $priority        [Optional] Priority level (default: 10).
	 * @param  int      $accepted_args   [Optional] Number of arguments the callback accepts (default: 1).
	 * @return void
	 */
	public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1)
	{
		$this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
	}

	/**
	 * Add a new filter to the collection.
	 *
	 * @since  1.0.0
	 * @param  string   $hook            The name of the WordPress filter.
	 * @param  object   $component       The object on which the callback method is defined.
	 * @param  string   $callback        The name of the method on $component.
	 * @param  int      $priority        [Optional] Priority level (default: 10).
	 * @param  int      $accepted_args   [Optional] Number of arguments the callback accepts (default: 1).
	 * @return void
	 */
	public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1)
	{
		$this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
	}

	/**
	 * Utility method to add a hook (action or filter) to a collection.
	 *
	 * @since   1.0.0
	 * @access  private
	 * @param   array    $hooks           The collection to add to (actions or filters).
	 * @param   string   $hook            The hook name in WordPress.
	 * @param   object   $component       The instance of the class containing the callback.
	 * @param   string   $callback        The method name to be called.
	 * @param   int      $priority        The execution priority.
	 * @param   int      $accepted_args   Number of arguments expected by the callback.
	 * @return  array                     Updated collection with the new hook.
	 */
	private function add($hooks, $hook, $component, $callback, $priority, $accepted_args)
	{
		$hooks[] = [
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		];

		return $hooks;
	}

	/**
	 * Register all actions and filters with WordPress.
	 *
	 * This method should be called during plugin bootstrap (e.g., in `Plugin::run()`).
	 * Iterates through stored hooks and binds them using `add_action()` and `add_filter()`.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function run()
	{
		foreach ($this->actions as $action) {
			add_action(
				$action['hook'],
				[$action['component'], $action['callback']],
				$action['priority'],
				$action['accepted_args']
			);
		}

		foreach ($this->filters as $filter) {
			add_filter(
				$filter['hook'],
				[$filter['component'], $filter['callback']],
				$filter['priority'],
				$filter['accepted_args']
			);
		}
	}
}

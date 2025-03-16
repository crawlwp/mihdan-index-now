<?php // phpcs:ignoreFile
/**
 * Simple DIC - DI Container in one file for WordPress and PHP Applications.
 * Supports autowiring and allows you to easily use it in your simple PHP applications and
 * especially convenient for WordPress plugins and themes and others.
 *
 * Author: Andrei Pisarevskii
 * Author Email: renakdup@gmail.com
 * Author Site: https://wp-yoda.com/en/
 *
 * Version: 1.2.2
 * Source Code: https://github.com/renakdup/simple-dic
 *
 * Licence: MIT License
 */

declare( strict_types=1 );

namespace Mihdan\IndexNow;

use Closure;
use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;

use function array_key_exists;
use function class_exists;
use function is_string;

class Container {
	/**
	 * @var mixed[]
	 */
	protected array $services = [];

	/**
	 * @var mixed[]
	 */
	protected array $resolved = [];

	/**
	 * @var array<string, ReflectionClass<object>>
	 */
	protected array $reflection_cache = [];

	public function __construct() {
		// Auto-register the container
		$this->resolved = [
			self::class => $this,
		];
	}

	/**
	 * Set service to the container. Allows to set configurable services
	 * using factory "function () {}" as passed service.
	 *
	 * @param mixed $service
	 */
	public function set( string $id, $service ): void {
		$this->services[ $id ] = $service;
		unset( $this->resolved[ $id ] );
		unset( $this->reflection_cache[ $id ] );
	}

	/**
	 * Finds an entry of the container by its identifier and returns it.
	 *
	 * @param string $id Identifier of the entry to look for.
	 *
	 * @return mixed Entry.
	 *
	 * @throws Exception Error while retrieving the entry.
	 *                  || No entry was found for **this** identifier.
	 */
	public function get( string $id ) {
		if ( isset( $this->resolved[ $id ] ) || array_key_exists( $id, $this->resolved ) ) {
			return $this->resolved[ $id ];
		}

		$service = $this->resolve( $id );
		$this->resolved[ $id ] = $service;

		return $service;
	}

	/**
	 * Returns true if the container can return an entry for the given identifier.
	 * Returns false otherwise.
	 *
	 * `has($id)` returning true does not mean that `get($id)` will not throw an exception.
	 * It does however mean that `get($id)` will not throw a `NotFoundExceptionInterface`.
	 *
	 * @param string $id Identifier of the entry to look for.
	 *
	 * @return bool
	 */
	public function has( string $id ): bool {
		return array_key_exists( $id, $this->resolved ) || array_key_exists( $id, $this->services );
	}

	/**
	 * Resolves service by its name. It returns a new instance of service every time, but the constructor's
	 * dependencies will not instantiate every time. If dependencies were resolved before
	 * then they will be passed as resolved dependencies.
	 *
	 * @throws Exception
	 */
	public function make( string $id ): object {
		if ( ! class_exists( $id ) ) {
			$message = "Service `{$id}` could not be resolved because class not exist.";
			throw new Exception( $message );
		}

		return $this->resolve_class( $id );
	}

	/**
	 * @return mixed
	 * @throws Exception
	 */
	protected function resolve( string $id ) {
		if ( $this->has( $id ) ) {
			$service = $this->services[ $id ];

			if ( $service instanceof Closure ) {
				return $service( $this );
			} elseif ( is_string( $service ) && class_exists( $service ) ) {
				return $this->resolve_class( $service );
			}

			return $service;
		}

		if ( class_exists( $id ) ) {
			return $this->resolve_class( $id );
		}

		throw new Exception( "Service `{$id}` not found in the Container." );
	}

	/**
	 * @param class-string $service
	 *
	 * @return object
	 * @throws Exception
	 */
	protected function resolve_class( string $service ): object {
		try {
			$reflected_class = $this->reflection_cache[ $service ] ?? new ReflectionClass( $service );

			$constructor = $reflected_class->getConstructor();

			if ( ! $constructor ) {
				return new $service();
			}

			$params = $constructor->getParameters();

			if ( ! $params ) {
				return new $service();
			}

			$resolved_params = $this->resolve_parameters( $params );
		} catch ( ReflectionException $e ) {
			throw new Exception(
				"Service `{$service}` could not be resolved due the reflection issue: `{$e->getMessage()}`"
			);
		}

		return new $service( ...$resolved_params );
	}

	/**
	 * @param ReflectionParameter[] $params
	 *
	 * @return mixed[]
	 * @throws Exception
	 * @throws ReflectionException
	 */
	protected function resolve_parameters( array $params ): array {
		$resolved_params = [];
		foreach ( $params as $param ) {
			$resolved_params[] = $this->resolve_param( $param );
		}

		return $resolved_params;
	}

	/**
	 * @param ReflectionParameter $param
	 *
	 * @return mixed|object
	 * @throws Exception
	 * @throws ReflectionException
	 */
	protected function resolve_param( ReflectionParameter $param ) {
		$param_type = $param->getType();

		if ( $param_type instanceof ReflectionNamedType && ! $param_type->isBuiltin() ) {
			return $this->get( $param_type->getName() );
		}

		if ( $param->isOptional() ) {
			return $param->getDefaultValue();
		}

		// @phpstan-ignore-next-line - Cannot call method getName() on ReflectionClass|null.
		$message = "Parameter `{$param->getName()}` of `{$param->getDeclaringClass()->getName()}` can't be resolved.";
		throw new Exception( $message );
	}
}

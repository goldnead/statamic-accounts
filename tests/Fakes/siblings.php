<?php

/*
 * Stand-ins for the sibling addons this addon does not require and the suite
 * therefore does not install: automations, webhook-manager, activity,
 * email-templates. Declared under their real names, because that is what the
 * bridges probe with `class_exists`. Each only records what it was handed,
 * and the two trigger stand-ins subscribe a listener the way the real
 * `registerEventTrigger()` does, so "event fired → trigger fired" is
 * observable.
 */

namespace Goldnead\StatamicAutomations\Facades {
    use Illuminate\Support\Facades\Event;

    if (! class_exists(Automations::class)) {
        class Automations
        {
            /** @var array<string, array{event: string, definition: array<string, mixed>}> */
            public static array $registered = [];

            /** @var list<array{handle: string, context: array<string, mixed>}> */
            public static array $dispatched = [];

            public static function getFacadeRoot(): object
            {
                return new class
                {
                    /** @param  array<string, mixed>  $definition */
                    public function registerEventTrigger(string $eventClass, array $definition): self
                    {
                        Automations::$registered[$definition['handle']] = ['event' => $eventClass, 'definition' => $definition];

                        Event::listen($eventClass, function ($event) use ($definition) {
                            Automations::$dispatched[] = [
                                'handle' => $definition['handle'],
                                'context' => ($definition['payload'])($event),
                            ];
                        });

                        return $this;
                    }
                };
            }
        }
    }
}

namespace Goldnead\WebhookManager\Facades {
    use Illuminate\Support\Facades\Event;

    if (! class_exists(WebhookManager::class)) {
        class WebhookManager
        {
            /** @var array<string, array{event: string, config: array<string, mixed>}> */
            public static array $registered = [];

            /** @var list<array{handle: string, payload: array<string, mixed>}> */
            public static array $fired = [];

            public static function getFacadeRoot(): object
            {
                return new class
                {
                    /** @param  array<string, mixed>  $config */
                    public function registerEventTrigger(string $eventClass, array $config = []): self
                    {
                        WebhookManager::$registered[$config['handle']] = ['event' => $eventClass, 'config' => $config];

                        Event::listen($eventClass, function ($event) use ($config) {
                            WebhookManager::$fired[] = [
                                'handle' => $config['handle'],
                                'payload' => ($config['payload'])($event),
                            ];
                        });

                        return $this;
                    }
                };
            }
        }
    }
}

namespace Goldnead\Activity\Facades {
    if (! class_exists(Activity::class)) {
        class Activity
        {
            /** @var list<array{type: string, attributes: array<string, mixed>}> */
            public static array $recorded = [];

            /** @param  array<string, mixed>  $attributes */
            public static function record(string $eventType, array $attributes = []): ?object
            {
                self::$recorded[] = ['type' => $eventType, 'attributes' => $attributes];

                return null;
            }
        }
    }
}

namespace Goldnead\EmailTemplates\Facades {
    if (! class_exists(EmailTemplates::class)) {
        class EmailTemplates
        {
            /** @var array<string, object> */
            public static array $templates = [];

            public static function resolve(string $slug, ?callable $fallback = null): ?object
            {
                return self::$templates[$slug] ?? null;
            }
        }
    }
}

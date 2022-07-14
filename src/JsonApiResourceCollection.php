<?php

declare(strict_types=1);

namespace TiMacDonald\JsonApi;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use TiMacDonald\JsonApi\Support\Cache;

class JsonApiResourceCollection extends AnonymousResourceCollection
{
    /**
     * @var array<Closure(RelationshipLink): void>
     */
    private array $relationshipLinkCallbacks = [];

    public function map(callable $callback): static
    {
        $this->collection = $this->collection->map($callback);

        return $this;
    }

    public function withRelationshipLink(Closure $callback): static
    {
        $this->relationshipLinkCallbacks[] = $callback;

        return $this;
    }

    /**
     * @param Request $request
     * @return array{included: Collection, jsonapi: JsonApiResource}
     */
    public function with($request): array
    {
        return [
            'included' => $this->collection
                ->map(static fn (JsonApiResource $resource): Collection => $resource->included($request))
                ->flatten()
                ->uniqueStrict(static fn (JsonApiResource $resource): string => $resource->toUniqueResourceIdentifier($request)),
            'jsonapi' => JsonApiResource::serverImplementationResolver()($request),
        ];
    }

    /**
     * @param Request $request
     */
    public function toResponse($request)
    {
        return tap(parent::toResponse($request)->header('Content-type', 'application/vnd.api+json'), fn () => Cache::flush($this));
    }

    /**
     * @param array<array-key, mixed> $paginated
     * @param array{links: array<string, ?string>} $default
     * @return array{links: array<string, string>}
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        $default['links'] = array_filter($default['links'], static fn (?string $link) => $link !== null);

        return $default;
    }

    /**
     * @internal
     * @return JsonApiResourceCollection<JsonApiResource>
     */
    public function withIncludePrefix(string $prefix): self
    {
        return tap($this, static function (JsonApiResourceCollection $resource) use ($prefix): void {
            $resource->collection->each(static fn (JsonApiResource $resource): JsonApiResource => $resource->withIncludePrefix($prefix));
        });
    }

    /**
     * @internal
     */
    public function included(Request $request): Collection
    {
        return $this->collection->map(static fn (JsonApiResource $resource): Collection => $resource->included($request));
    }

    public function toResourceLink(Request $request): RelationshipCollectionLink
    {
        $resourceLinks = $this->collection
            ->uniqueStrict(static fn (JsonApiResource $resource): string => $resource->toUniqueResourceIdentifier($request))
            ->map(static fn (JsonApiResource $resource): ResourceIdentifier => $resource->resolveResourceIdentifier($request));

        return new RelationshipCollectionLink($resourceLinks->all());
    }

    /**
     * @internal
     */
    public function resolveRelationshipLink(Request $request): RelationshipCollectionLink
    {
        return tap($this->toResourceLink($request), function (RelationshipCollectionLink $link) {
            foreach ($this->relationshipLinkCallbacks as $callback) {
                $callback($link);
            }
        });
    }

    /**
     * @internal
     */
    public function includable(): Collection
    {
        return $this->collection;
    }

    /**
     * @internal
     * @infection-ignore-all
     */
    public function flush(): void
    {
        $this->collection->each(static fn (JsonApiResource $resource) => $resource->flush());
    }
}

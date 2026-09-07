<?php

namespace App\Ai\Tools;

use App\Models\Agency;
use App\Services\Chatbot\ChatbotTurn;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class GetAgencyServices implements Tool
{
    public function __construct(private readonly ChatbotTurn $turn) {}

    public function description(): string
    {
        return 'Read public directory details, services and requirements for an agency by exact name, abbreviation or slug. No private case data. This is directory evidence, not a helpdesk article.';
    }

    public function schema(JsonSchema $schema): array
    {
        return ['agency' => $schema->string()->required()];
    }

    public function handle(Request $request): string
    {
        $this->turn->call();
        $name = $request->all()['agency'] ?? null;
        if (! is_string($name) || mb_strlen($name) > 200 || trim($name) === '') {
            return '{"status":"invalid_arguments"}';
        }
        $name = mb_strtolower(trim($name));
        $agency = Agency::query()->where('is_active', true)->where('is_deleted', false)
            ->where(fn ($q) => $q->whereRaw('lower(slug) = ?', [$name])->orWhereRaw('lower(name) = ?', [$name])->orWhereRaw('lower(short) = ?', [$name]))
            ->first(['id', 'slug', 'name', 'short', 'description', 'contact_info', 'location_query']);
        if (! $agency) {
            return '{"status":"not_found"}';
        }
        $services = $agency->services()->where('is_deleted', false)->orderBy('name')->with(['requirements' => fn ($q) => $q->where('is_deleted', false)->orderBy('name')])->get();
        $content = json_encode([
            'name' => $agency->name, 'short' => $agency->short, 'description' => $agency->description,
            'contact_info' => $agency->contact_info, 'location' => $agency->location_query,
            'services' => $services->map(fn ($service) => [
                'name' => $service->name, 'description' => $service->description, 'processing_days' => $service->processing_days,
                'requirements' => $service->requirements->map(fn ($r) => ['name' => $r->name, 'is_required' => $r->is_required])->all(),
            ])->all(),
        ], JSON_THROW_ON_ERROR);
        if (mb_strlen($content) > $this->turn->availableCharacters()) {
            return '{"status":"content_limit","message":"Directory record is too large; no partial list has been supplied."}';
        }
        $source = ['source_type' => 'reference', 'slug' => $agency->slug, 'heading' => $agency->name.' — Agency directory', 'url' => null, 'sections' => []];

        return json_encode(['status' => 'success', 'evidence_id' => $this->turn->register($source, $content), 'content' => json_decode($content, true)], JSON_THROW_ON_ERROR);
    }
}

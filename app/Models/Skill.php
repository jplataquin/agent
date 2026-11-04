<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Skill extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'instructions',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(SkillCategory::class, 'category_id');
    }

    /**
     * Resolves templating syntax like {@skill-slug} within the provided text.
     * Replaces the tag with the instructions of the referenced skill.
     * Prevents infinite recursion by tracking visited slugs.
     */
    public static function resolveTemplate(?string $text, array $visited = []): string
    {
        if (empty($text)) {
            return '';
        }

        return preg_replace_callback('/\{@([^}]+)\}/', function ($matches) use ($visited) {
            $slug = trim($matches[1]);

            if (in_array($slug, $visited, true)) {
                return "[Circular reference detected for skill: {$slug}]";
            }

            $skill = self::where('slug', $slug)->first();

            if (! $skill) {
                return "[Skill not found: {$slug}]";
            }

            $visited[] = $slug;

            return self::resolveTemplate($skill->instructions, $visited);
        }, $text);
    }
}

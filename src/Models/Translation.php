<?php

namespace Qubiqx\QcommerceTranslations\Models;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

class Translation extends Model
{
    use SoftDeletes;
    use HasTranslations;
    use LogsActivity;

    protected static $logFillable = true;

    protected $fillable = [
        'tag',
        'name',
        'value',
        'default',
        'type',
        'variables',
    ];

    public $translatable = [
        'value',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'variables' => 'array',
    ];

    protected $table = 'qcommerce__translations';

    public static function get($name, $tag, $default = null, $type = 'text', $variables = null)
    {
        if ($name && ! $default) {
            $default = $name;
            $name = Str::slug($name);
        }

        if ($variables) {
            return self::getByParams($name, $tag, $default, $type, $variables);
        }

        $result = Cache::rememberForever(Str::slug($name . $tag . app()->getLocale()), function () use ($name, $tag, $default, $type, $variables) {
            return self::getByParams($name, $tag, $default, $type, $variables);
        });

        return $result;
    }

    public static function getByParams($name, $tag, $default, $type, $variables)
    {
        if ($default == null) {
            $default = $name;
        }
        $translation = self::where('name', $name)->where('tag', $tag)->first();
        if (! $translation) {
            $translation = self::withTrashed()->where('name', $name)->where('tag', $tag)->first();
            if (! $translation) {
                $translation = self::updateOrCreate(
                    ['name' => $name, 'tag' => $tag],
                    ['default' => $default, 'type' => $type, 'variables' => $variables]
                );

                if ($variables) {
                    foreach ($variables as $key => $variable) {
                        $default = str_replace(':' . $key . ':', $variable, $default);
                    }
                }

                return $default == $name ? '' : $default;
            } else {
                $translation->restore();
            }
        }

        if ($translation && $translation->type != $type) {
            $translation->type = $type;
            $translation->save();
        }

        if ($translation && $default && $translation->default != $default) {
            $translation->default = $default;
            $translation->save();
        }
        if ($translation && $translation->value) {
            if ($variables) {
                foreach ($variables as $key => $variable) {
                    $translation->value = str_replace(':' . $key . ':', $variable, $translation->value);
                }
            }

            return $translation->value;
        } else {
            if ($translation->default) {
                if ($variables) {
                    foreach ($variables as $key => $variable) {
                        $translation->default = str_replace(':' . $key . ':', $variable, $translation->default);
                    }
                }

                return $translation->default;
            } else {
                if ($variables) {
                    foreach ($variables as $key => $variable) {
                        $default = str_replace(':' . $key . ':', $variable, $default);
                    }
                }

                return $default == $name ? '' : $default;
            }
        }
    }
}

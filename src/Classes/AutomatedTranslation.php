<?php

namespace Dashed\DashedTranslations\Classes;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Jobs\TranslateValueFromModel;
use Dashed\DashedTranslations\Models\AutomatedTranslationProgress;
use Dashed\Deepl\Facades\Deepl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class AutomatedTranslation
{
    public static function automatedTranslationsEnabled()
    {
        return ! is_null(self::getProvider());
    }

    public static function getProvider(): ?array
    {
        if (Customsetting::get('deepl_translations_enabled') && Customsetting::get('deepl_api_key')) {
            return [
                'provider' => 'deepl',
                'api_key' => Customsetting::get('deepl_api_key'),
            ];
        }

        return null;
    }

    public static function translate(string $text, string $targetLanguage, string $sourceLanguage): string
    {
        $provider = self::getProvider();

        if (! $provider) {
            throw new \Exception('No translation provider enabled');
        }

        if ($provider['provider'] === 'deepl') {
            Config::set('deepl.api_key', $provider['api_key']);

            return Deepl::api()->translate($text, $targetLanguage, $sourceLanguage);
        }
    }

    public static function translateModel(Model $model, string $fromLocale, array $toLocales): void
    {
        $totalColumnsToTranslate = 0;
        $automatedTranslationProgresses = [];

        foreach ($toLocales as $toLocale) {
            $automatedTranslationProgress = new AutomatedTranslationProgress();
            $automatedTranslationProgress->model_type = $model::class;
            $automatedTranslationProgress->model_id = $model->id;
            $automatedTranslationProgress->from_locale = $fromLocale;
            $automatedTranslationProgress->to_locale = $toLocale;
            $automatedTranslationProgress->save();
            $automatedTranslationProgresses[$toLocale] = $automatedTranslationProgress;
        }

        foreach ($model->translatable as $column) {
            if (! method_exists($model, $column)) {
                $totalColumnsToTranslate++;
                $textToTranslate = $model->getTranslation($column, $fromLocale);

                foreach ($toLocales as $locale) {
                    TranslateValueFromModel::dispatch($model, $column, $textToTranslate, $locale, $fromLocale, [], $automatedTranslationProgresses[$locale]);
                }
            }
        }

        if ($model->metadata) {
            $translatableMetaColumns = [
                'title',
                'description',
            ];

            foreach ($translatableMetaColumns as $column) {
                $totalColumnsToTranslate++;
                $textToTranslate = $model->metadata->getTranslation($column, $fromLocale);
                foreach ($toLocales as $locale) {
                    TranslateValueFromModel::dispatch($model->metadata, $column, $textToTranslate, $locale, $fromLocale, [], $automatedTranslationProgresses[$locale]);
                }
            }
        }

        if ($model->customBlocks) {
            $translatableCustomBlockColumns = [
                'blocks',
            ];

            foreach ($translatableCustomBlockColumns as $column) {
                $totalColumnsToTranslate++;
                $textToTranslate = $model->customBlocks->getTranslation($column, $fromLocale);
                foreach ($toLocales as $locale) {
                    TranslateValueFromModel::dispatch($model->customBlocks, $column, $textToTranslate, $locale, $fromLocale, [
                        'customBlock' => str($model::class . 'Blocks')->explode('\\')->last(),
                    ], $automatedTranslationProgresses[$locale]);
                }
            }
        }

        foreach ($toLocales as $toLocale) {
            $automatedTranslationProgress = AutomatedTranslationProgress::where('model_type', $model::class)
                ->where('model_id', $model->id)
                ->where('from_locale', $fromLocale)
                ->where('to_locale', $toLocale)
                ->latest()
                ->first();
            $automatedTranslationProgress->total_columns_to_translate = $totalColumnsToTranslate;
            $automatedTranslationProgress->save();
        }
    }
}

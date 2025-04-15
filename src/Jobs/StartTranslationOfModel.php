<?php

namespace Dashed\DashedTranslations\Jobs;

use Dashed\DashedTranslations\Classes\AutomatedTranslation;
use Dashed\DashedTranslations\Models\AutomatedTranslationProgress;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StartTranslationOfModel implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $timeout = 3000;
    public $tries = 1000;
    public $model;
    public $fromLocale;
    public $toLocales;
    public $overwriteColumns;
    public ?AutomatedTranslationProgress $automatedTranslationProgress;

    /**
     * Create a new job instance.
     */
    public function __construct(Model $model, string $fromLocale, array $toLocales, array $overwriteColumns = [], ?AutomatedTranslationProgress $automatedTranslationProgress = null)
    {
        $this->model = $model;
        $this->fromLocale = $fromLocale;
        $this->toLocales = $toLocales;
        $this->overwriteColumns = $overwriteColumns;
        $this->automatedTranslationProgress = $automatedTranslationProgress;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $model = $this->model;
        $fromLocale = $this->fromLocale;
        $toLocales = $this->toLocales;
        $overwriteColumns = $this->overwriteColumns;
        $automatedTranslationProgress = $this->automatedTranslationProgress;

        //        try {
        //            $waitMinutes = 0;

        //            $totalStringsToTranslate = 0;
        if ($automatedTranslationProgress) {
            $automatedTranslationProgress->total_strings_to_translate = 0;
            $automatedTranslationProgress->total_strings_translated = 0;
            $automatedTranslationProgress->error = null;
            $automatedTranslationProgress->save();
        }

        $automatedTranslationProgresses = [];

        if (count($toLocales) == 1 && $automatedTranslationProgress) {
            $automatedTranslationProgresses[$toLocales[array_key_first($toLocales)]] = $automatedTranslationProgress;
        } else {
            foreach ($toLocales as $toLocale) {
                $automatedTranslationProgress = AutomatedTranslationProgress::where('model_type', $model::class)
                    ->where('model_id', $model->id)
                    ->where('from_locale', $fromLocale)
                    ->where('to_locale', $toLocale)
                    ->where('status', '!=', 'finished')
                    ->latest()
                    ->first();
                if (! $automatedTranslationProgress) {
                    $automatedTranslationProgress = new AutomatedTranslationProgress();
                    $automatedTranslationProgress->model_type = $model::class;
                    $automatedTranslationProgress->model_id = $model->id;
                    $automatedTranslationProgress->from_locale = $fromLocale;
                    $automatedTranslationProgress->to_locale = $toLocale;
                }
                $automatedTranslationProgress->error = null;
                $automatedTranslationProgress->total_strings_to_translate = 0;
                $automatedTranslationProgress->total_strings_translated = 0;
                $automatedTranslationProgress->save();
                $automatedTranslationProgresses[$toLocale] = $automatedTranslationProgress;
            }
        }

        foreach ($model->translatable as $column) {
            if (! method_exists($model, $column) || in_array($column, $overwriteColumns)) {
                //                    $totalStringsToTranslate++;
                $textToTranslate = $model->getTranslation($column, $fromLocale);

                foreach ($toLocales as $locale) {
                    ExtractStringsToTranslate::dispatch($model, $column, $textToTranslate, $locale, $fromLocale, [], $automatedTranslationProgresses[$locale]);
                    //                            ->delay(now()->addMinutes($waitMinutes));
                    //                        $waitMinutes++;
                }
            }
        }

        if ($model->metadata) {
            $translatableMetaColumns = [
                'title',
                'description',
            ];

            foreach ($translatableMetaColumns as $column) {
                //                    $totalStringsToTranslate++;
                $textToTranslate = $model->metadata->getTranslation($column, $fromLocale);
                foreach ($toLocales as $locale) {
                    ExtractStringsToTranslate::dispatch($model->metadata, $column, $textToTranslate, $locale, $fromLocale, [], $automatedTranslationProgresses[$locale]);
                    //                            ->delay(now()->addMinutes($waitMinutes));
                    //                        $waitMinutes++;
                }
            }
        }

        if ($model->customBlocks) {
            $translatableCustomBlockColumns = [
                'blocks',
            ];

            foreach ($translatableCustomBlockColumns as $column) {
                //                    $totalStringsToTranslate++;
                $textToTranslate = $model->customBlocks->getTranslation($column, $fromLocale);
                foreach ($toLocales as $locale) {
                    ExtractStringsToTranslate::dispatch($model->customBlocks, $column, $textToTranslate, $locale, $fromLocale, [
                        'customBlock' => str($model::class . 'Blocks')->explode('\\')->last(),
                    ], $automatedTranslationProgresses[$locale]);
                    //                            ->delay(now()->addMinutes($waitMinutes));
                    //                        $waitMinutes++;
                }
            }
        }

        //            foreach ($toLocales as $toLocale) {
        //                $automatedTranslationProgress = AutomatedTranslationProgress::where('model_type', $model::class)
        //                    ->where('model_id', $model->id)
        //                    ->where('from_locale', $fromLocale)
        //                    ->where('to_locale', $toLocale)
        //                    ->latest()
        //                    ->first();
        //                $automatedTranslationProgress->total_strings_to_translate = $totalStringsToTranslate;
        //                $automatedTranslationProgress->save();
        //            }
        //        } catch (\Exception $exception) {
        //            $this->failed($exception);
        //        }
    }

    //    public function failed($exception)
    //    {
    //        if (str($exception->getMessage())->contains('Too many requests')) {
    //            $this->automatedTranslationProgress->status = 'retrying';
    //            $this->automatedTranslationProgress->error = 'Opnieuw proberen i.v.m. rate limiting';
    //            $this->automatedTranslationProgress->save();
    //            StartTranslationOfModel::dispatch($this->model, $this->column, $this->value, $this->toLanguage, $this->fromLanguage, $this->attributes, $this->automatedTranslationProgress)
    //                ->delay(now()->addMinutes(2));
    //        } else {
    //            $this->automatedTranslationProgress->status = 'error';
    //            $this->automatedTranslationProgress->error = $exception->getMessage();
    //            $this->automatedTranslationProgress->save();
    //        }
    //    }

    private function searchAndTranslate(&$array, $parentKeys = [])
    {
        foreach ($array as $key => &$value) {
            if (! is_int($key) && $key != 'data') {
                $currentKeys = array_merge($parentKeys, [$key]);
            } else {
                $currentKeys = $parentKeys;
            }

            if (is_array($value)) {
                if (array_key_exists('type', $value) && array_key_exists('data', $value)) {
                    $currentKeys = array_merge($parentKeys, [$value['type']]);
                }
                $this->searchAndTranslate($value, $currentKeys);
            } elseif (! str($key)->contains('type') && ! str($key)->contains('url')) {
                $builderBlock = $this->matchBuilderBlock($key, $parentKeys, cms()->builder('blocks')) || $this->matchCustomBlock($key, $parentKeys, cms()->builder($this->attributes['customBlock'] ?? 'blocks'));
                if ($builderBlock && ($builderBlock instanceof Select || $builderBlock instanceof Toggle || $builderBlock instanceof FileUpload)) {
                    continue;
                }

                $value = $this->translate($value);
            }
        }

        unset($value);
    }

    private function matchBuilderBlock($key, $parentKeys, $blocks, $currentBlock = null)
    {
        if (count($parentKeys) || (! count($parentKeys) && $currentBlock)) {
            foreach ($blocks as $block) {
                if (count($parentKeys) && $block->getName() === $parentKeys[0]) {
                    $currentBlock = $block;
                } elseif ($currentBlock && method_exists($block, 'getName') && $block->getName() === $key) {
                    return $block;
                }
            }
        }

        if ($currentBlock && count($parentKeys)) {
            $currentBlock = $this->matchBuilderBlock($key, array_slice($parentKeys, 1), $currentBlock->getChildComponents(), $currentBlock);
        }

        if ($currentBlock && $currentBlock->getName() === $key) {
            return $currentBlock;
        }

        return null;
    }

    private function matchCustomBlock($key, $parentKeys, $blocks, $currentBlock = null)
    {
        if (count($parentKeys) || (! count($parentKeys) && $currentBlock)) {
            foreach ($blocks as $block) {
                if (count($parentKeys) && $block->getName() === $parentKeys[0]) {
                    $currentBlock = $block;
                } elseif ($currentBlock && method_exists($block, 'getName') && $block->getName() === $key) {
                    return $block;
                }
            }
        }

        if ($currentBlock && count($parentKeys)) {
            $currentBlock = $this->matchCustomBlock($key, array_slice($parentKeys, 1), $currentBlock->getChildComponents(), $currentBlock);
        }

        if ($currentBlock && $currentBlock->getName() === $key) {
            return $currentBlock;
        }

        return null;
    }

    private function translate(?string $value = '')
    {
        if (! $value) {
            return $value;
        }

        sleep(5);

        return AutomatedTranslation::translate($value, $this->toLanguage, $this->fromLanguage);
    }
}

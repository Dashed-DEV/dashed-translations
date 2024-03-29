<?php

namespace Dashed\DashedTranslations\Filament\Resources\TranslationResource\Pages;

use Carbon\Carbon;
use Dashed\DashedCore\Classes\Locales;
use Dashed\DashedTranslations\Filament\Resources\TranslationResource;
use Dashed\DashedTranslations\Models\Translation;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use FilamentTiptapEditor\TiptapEditor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ListTranslations extends Page
{
    protected static string $resource = TranslationResource::class;
    protected static string $view = 'dashed-translations::translations.pages.list-translations';
    public $data;

    public function mount(): void
    {
        $formData = [];
        $translations = Translation::all();
        foreach ($translations as $translation) {
            foreach (Locales::getLocales() as $locale) {
                if ($translation->type == 'datetime') {
                    $formData["translation_{$translation->id}_{$locale['id']}"] = Carbon::parse($translation->getTranslation('value', $locale['id']) ?: $translation->default)->format('Y-m-d H:i:s');
                } else {
                    $formData["translation_{$translation->id}_{$locale['id']}"] = $translation->getTranslation('value', $locale['id']);
                }
            }
        }

        $this->form->fill($formData);
    }

    public function getFormStatePath(): ?string
    {
        return 'data';
    }

    protected function getFormSchema(): array
    {
        $tags = Translation::distinct('tag')->orderBy('tag', 'ASC')->pluck('tag');
        $sections = [];


        foreach ($tags as $tag) {
            $translations = Translation::where('tag', $tag)->orderBy('name', 'ASC')->get();
            $tabs = [];

            foreach (Locales::getLocales() as $locale) {
                $schema = [];

                foreach ($translations as $translation) {
                    $helperText = '';
                    if ($translation->variables && is_array($translation->variables)) {
                        $helperText = 'Beschikbare variablen: <br>';

                        foreach ($translation->variables as $key => $value) {
                            $helperText .= ":$key: (bijv: $value) <br>";
                        }
                    }

                    if ($translation->type == 'textarea') {
                        $schema[] = Textarea::make("translation_{$translation->id}_{$locale['id']}")
                            ->placeholder($translation->default)
                            ->rows(5)
                            ->label(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title())
                            ->helperText($helperText ?? '')
                            ->lazy()
                            ->afterStateUpdated(function (Textarea $component, Set $set, $state) {
                                $explode = explode('_', $component->getStatePath());
                                $translationId = $explode[1];
                                $locale = $explode[2];
                                $translation = Translation::find($translationId);
                                $translation->setTranslation("value", $locale, $state);
                                $translation->save();
                                Cache::forget(Str::slug($translation->name . $translation->tag . $locale . $translation->type));
                                Notification::make()
                                    ->title(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title() . " is opgeslagen")
                                    ->success()
                                    ->send();
                            });
                    } elseif ($translation->type == 'datetime') {
                        $schema[] = DateTimePicker::make("translation_{$translation->id}_{$locale['id']}")
                            ->placeholder(Carbon::parse($translation->default)->format('Y-m-d H:i:s'))
                            ->label(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title())
                            ->helperText($helperText ?? '')
                            ->reactive()
                            ->afterStateUpdated(function (DateTimePicker $component, Set $set, $state) {
                                $explode = explode('_', $component->getStatePath());
                                $translationId = $explode[1];
                                $locale = $explode[2];
                                $translation = Translation::find($translationId);
                                $translation->setTranslation("value", $locale, $state);
                                $translation->save();
                                Cache::forget(Str::slug($translation->name . $translation->tag . $locale . $translation->type));
                                Notification::make()
                                    ->title(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title() . " is opgeslagen")
                                    ->success()
                                    ->send();
                            });
                    } elseif ($translation->type == 'editor') {
                        $schema[] = TiptapEditor::make("translation_{$translation->id}_{$locale['id']}")
//                            ->placeholder($translation->default)
                            ->label(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title())
                            ->helperText($helperText ?? '')
                            ->live()
                            ->afterStateUpdated(function (TiptapEditor $component, Set $set, $state) {
                                //                                $explode = explode('_', $component->getStatePath());
                                //                                $translationId = $explode[1];
                                //                                $locale = $explode[2];
                                //                                $translation = Translation::find($translationId);
                                //                                $translation->setTranslation("value", $locale, $state);
                                //                                $translation->save();
                                //                                Cache::forget(Str::slug($translation->name . $translation->tag . $locale . $translation->type));
                                //                                Notification::make()
                                //                                    ->title(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title() . " is opgeslagen")
                                //                                    ->success()
                                //                                    ->send();
                            });
                    } elseif ($translation->type == 'image') {
                        $schema[] = FileUpload::make("translation_{$translation->id}_{$locale['id']}")
                            ->disk('dashed')
                            ->default($translation->default)
                            ->downloadable()
                            ->openable()
                            ->label(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title())
                            ->helperText($helperText ?? '');
                    } elseif ($translation->type == 'repeater') {
                        $schema[] = Repeater::make("translation_{$translation->id}_{$locale['id']}")
                            ->label(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title())
                            ->schema(cms()->builder('translationRepeaters')[$translation->name] ?? [])
                            ->helperText($helperText ?? '')
                            ->reorderable()
                            ->cloneable()
                            ->reactive();
                    } else {
                        $schema[] = TextInput::make("translation_{$translation->id}_{$locale['id']}")
                            ->placeholder($translation->default)
                            ->label(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title())
                            ->helperText($helperText ?? '')
                            ->lazy()
                            ->afterStateUpdated(function (TextInput $component, Set $set, $state) {
                                $explode = explode('_', $component->getStatePath());
                                $translationId = $explode[1];
                                $locale = $explode[2];
                                $translation = Translation::find($translationId);
                                $translation->setTranslation("value", $locale, $state);
                                $translation->save();
                                Cache::forget(Str::slug($translation->name . $translation->tag . $locale . $translation->type));
                                Notification::make()
                                    ->title(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title() . " is opgeslagen")
                                    ->success()
                                    ->send();
                            });
                    }
                }

                $tabs[] = Tab::make($locale['id'])
                    ->label(strtoupper($locale['id']))
                    ->schema($schema);
            }

            $sections[] = Section::make('Vertalingen voor ' . $tag)
                ->schema([
                    Tabs::make('Locales')
                        ->tabs($tabs),
                ])
                ->collapsible();
        }

        return $sections;
    }

    public function updated($path, $value): void
    {
        foreach (Translation::where('type', 'image')->get() as $translation) {
            foreach (Locales::getLocales() as $locale) {
                if (Str::contains($path, "translation_{$translation->id}_{$locale['id']}")) {
                    Notification::make()
                        ->title(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title() . " wordt opgeslagen")
                        ->success()
                        ->send();
                    $imagePath = $value->store('/dashed/translations', 'dashed');
                    $explode = explode('.', $path);
                    $explode = explode('_', $explode[1]);
                    $translationId = $explode[1];
                    $locale = $explode[2];
                    $translation = Translation::find($translationId);
                    $translation->setTranslation("value", $locale, $imagePath);
                    $translation->save();
                    Cache::forget(Str::slug($translation->name . $translation->tag . $locale . $translation->type));
                    Notification::make()
                        ->title(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title() . " is opgeslagen")
                        ->success()
                        ->send();
                }
            }
        }

        foreach (Translation::where('type', 'editor')->get() as $translation) {
            foreach (Locales::getLocales() as $locale) {
                if (Str::contains($path, "translation_{$translation->id}_{$locale['id']}")) {
                    $value = $this->form->getState()["translation_{$translation->id}_{$locale['id']}"];
                    $explode = explode('.', $path);
                    $explode = explode('_', $explode[1]);
                    $translationId = $explode[1];
                    $locale = $explode[2];
                    $translation = Translation::find($translationId);
                    $translation->setTranslation("value", $locale, $value);
                    $translation->save();
                    Cache::forget(Str::slug($translation->name . $translation->tag . $locale . $translation->type));
                    Notification::make()
                        ->title(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title() . " is opgeslagen")
                        ->success()
                        ->send();
                }
            }
        }

        foreach (Translation::where('type', 'repeater')->get() as $translation) {
            foreach (Locales::getLocales() as $locale) {
                if (Str::contains($path, "translation_{$translation->id}_{$locale['id']}")) {
                    $explode = explode('_', $path);
                    $translationId = $explode[1];
                    $locale = explode('.', $explode[2])[0];
                    $translation = Translation::find($translationId);
                    $translation->setTranslation("value", $locale, $this->data["translation_{$translation->id}_{$locale}"]);
                    $translation->save();
                    Cache::forget(Str::slug($translation->name . $translation->tag . $locale . $translation->type));
                    Notification::make()
                        ->title(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title() . " is opgeslagen")
                        ->success()
                        ->send();
                }
            }
        }
    }
}

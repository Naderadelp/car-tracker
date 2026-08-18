<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Filament\Support\PermissionGroups;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoleForm
{
    /**
     * The form state key holding the grouped permission checkboxes.
     *
     * This is not a model attribute: App\Filament\Resources\Roles\Concerns\
     * SyncsGroupedPermissions strips it before save and turns it into a
     * syncPermissions() call.
     */
    public const STATE_KEY = 'permission_groups';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Role')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(125)
                            ->unique(ignoreRecord: true)
                            ->helperText('Roles are always stored under the "api" permission guard.'),
                    ]),
                Section::make('Permissions')
                    ->description('Grouped by the subject each permission acts on.')
                    ->collapsible()
                    ->schema(fn (): array => collect(PermissionGroups::all())
                        ->map(fn (array $options, string $subject) => Section::make(PermissionGroups::groupLabel($subject))
                            ->compact()
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                CheckboxList::make(self::STATE_KEY.'.'.$subject)
                                    ->hiddenLabel()
                                    ->options($options)
                                    ->columns(3)
                                    ->gridDirection('row')
                                    ->bulkToggleable(),
                            ]))
                        ->values()
                        ->all()),
            ]);
    }
}

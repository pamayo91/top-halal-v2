<?php
namespace App\Filament\Pages;
use Filament\Pages\Dashboard;
class AdminDashboard extends Dashboard
{
    protected static ?string $navigationLabel = 'Tableau de bord';
    protected static ?string $title = 'Tableau de bord';
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-home';
    protected static ?int $navigationSort = -10;
}

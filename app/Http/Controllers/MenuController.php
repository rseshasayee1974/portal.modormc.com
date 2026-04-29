<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class MenuController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'menus';

    public function index()
    {
        $this->authorizeModule('menu');
        
        return Inertia::render('Menus/Index', [
            'menus' => Menu::with('children')->orderBy('ordering')->get(),
            'allMenus' => Menu::orderBy('title')->get(), // For parent selection
            'menutypes' => [
                ['label' => 'Top Bar', 'value' => 1],
                ['label' => 'Sidebar/Submenu', 'value' => 2],
            ],
            'iconList' => [
                'HomeIcon', 'Square3Stack3DIcon', 'CreditCardIcon', 'Cog6ToothIcon', 'SwatchIcon', 
                'ClipboardDocumentListIcon', 'IdentificationIcon', 'BriefcaseIcon', 'ChartBarIcon', 
                'BellIcon', 'UserCircleIcon', 'ArrowUpRightIcon', 'ArrowDownLeftIcon', 'ClockIcon', 
                'DocumentTextIcon', 'ArrowDownOnSquareIcon', 'ArrowUpOnSquareIcon', 'ChartPieIcon', 
                'CogIcon', 'UserPlusIcon', 'ReceiptPercentIcon', 'ScaleIcon', 'BanknotesIcon', 
                'BuildingOfficeIcon', 'UsersIcon', 'ShieldCheckIcon', 'KeyIcon', 'PhoneIcon', 
                'GlobeAltIcon', 'CurrencyDollarIcon', 'MapIcon', 'MapPinIcon', 'ArrowPathIcon', 
                'BookOpenIcon', 'DocumentDuplicateIcon', 'DocumentChartBarIcon', 'UserGroupIcon', 'TruckIcon'
            ]
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');
        
        $validated = $request->validate([
            'title' => 'required|string|max:100',
            'alias' => 'nullable|string|max:100',
            'link' => 'required|string',
            'icon' => 'nullable|string|max:100',
            'menutype' => 'required|integer',
            'parent_id' => 'required|integer',
            'ordering' => 'required|integer',
            'published' => 'required|boolean',
            'permission_name' => 'nullable|string|max:100',
        ]);

        Menu::create($validated);

        return redirect()->back()->with('success', 'Menu item added successfully.');
    }

    public function update(Request $request, Menu $menu)
    {
        $this->authorizeModule('edit');
        
        $validated = $request->validate([
            'title' => 'required|string|max:100',
            'alias' => 'nullable|string|max:100',
            'link' => 'required|string',
            'icon' => 'nullable|string|max:100',
            'menutype' => 'required|integer',
            'parent_id' => 'required|integer',
            'ordering' => 'required|integer',
            'published' => 'required|boolean',
            'permission_name' => 'nullable|string|max:100',
        ]);

        $menu->update($validated);

        return redirect()->back()->with('success', 'Menu item updated successfully.');
    }

    public function destroy(Menu $menu)
    {
        $this->authorizeModule('delete');
        $menu->delete();

        return redirect()->back()->with('success', 'Menu item removed successfully.');
    }
}

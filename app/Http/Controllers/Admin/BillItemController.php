<?php /** @noinspection DuplicatedCode */

namespace App\Http\Controllers\Admin;

use App\Enums\Core\BillItemType;
use App\Enums\Files\FileType;
use App\Exceptions\LogicException;
use App\Http\Controllers\Controller;
use App\Models\AccountAddon;
use App\Models\Addon;
use App\Models\AddonOption;
use App\Models\BillCategory;
use App\Models\BillItem;
use App\Models\BillItemFaq;
use App\Models\BillItemMeta;
use App\Models\BillItemTag;
use App\Models\QuoteItemAddon;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Operations\API\Control;
use App\Operations\Core\LoFileHandler;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;


class BillItemController extends Controller
{
    /**
     * Generate Proper Breadcrumbs for BillItems
     * @param BillCategory  $cat
     * @param BillItem|null $item
     * @param               $forceItem
     * @return string[]
     */
    private function generateCrumbs(BillCategory $cat, ?BillItem $item = null, $forceItem = false): array
    {
        if ($cat->type == 'services')
        {
            $crumbs = ["/admin/bill_categories/services" => 'Services'];
            if (!$item && !$forceItem)
            {
                $crumbs[''] = $cat->name;
            }
        }
        else
        {
            $crumbs = ["/admin/bill_categories/products" => 'Products'];
        }
        if (!$item && !$forceItem)
        {
            // $crumbs[] = $cat->name;
        }
        else
        {
            $crumbs["/admin/category/$cat->id/items"] = $cat->name;
            $crumbs['0'] = $item ? $item->name : "Create new " . Str::singular($cat->type);
        }
        return $crumbs;
    }

    /**
     * Show all categories
     * @param BillCategory $cat
     * @return View
     */
    public function index(BillCategory $cat): View
    {
        $crumbs = $this->generateCrumbs($cat);
        return view('admin.bill_items.index', ['cat' => $cat, 'crumbs' => $crumbs]);
    }

    /**
     * Show create form for new product/service
     * @param BillCategory $cat
     * @return View
     */
    public function create(BillCategory $cat): View
    {
        $type = Str::singular($cat->type);
        return view("admin.bill_items.$type", [
            'cat'    => $cat,
            'item'   => new BillItem,
            'type'   => $type,
            'crumbs' => $this->generateCrumbs($cat, null, true)
        ]);
    }

    /**
     * Import from Control
     * @param string $lid
     * @return View
     * @throws GuzzleException
     * @throws LogicException
     */
    public function import(string $lid): View
    {
        $c = new Control();
        $item = $c->getItem($lid);
        return view('admin.bill_items.import')->with('item', $item);
    }

    /**
     * Import product from control.
     * @param string  $lid
     * @param Request $request
     * @return RedirectResponse
     * @throws GuzzleException
     * @throws LogicException
     */
    public function importProcess(string $lid, Request $request): RedirectResponse
    {
        $c = new Control();
        $item = $c->getItem($lid);
        $i = (new BillItem)->create([
            'bill_category_id'      => $request->category_id,
            'code'                  => strtoupper(Str::slug($item->name)),
            'name'                  => $item->name,
            'description'           => $item->import_description,
            'feature_headline'      => $item->feature_headline,
            'feature_list'          => $item->feature_list,
            'photo_id'              => 0,
            'slick_id'              => 0,
            'is_shipped'            => (bool)$item->is_shipped,
            'marketing_description' => $item->description,
            'type'                  => $item->product ? 'products' : "services",
            'lid'                   => $item->lid,
            'slug'                  => Str::slug($item->name),
            'msrp'                  => $request->price * 100,
        ]);
        if ($item->product)
        {
            $i->update(['nrc' => $request->price * 100]);
        }
        else $i->update(['mrc' => $request->price * 100]);
        if ($item->logo_id)
        {
            $lo = new LoFileHandler();
            $file = $lo->create($i->code . ".png", FileType::Image, $i->id, $item->logo_id,
                'image/png');
            $lo->unlock($file);
            $i->update(['photo_id' => $file->id]);
        }

        if ($item->photo_1)
        {
            $lo = new LoFileHandler();
            $file = $lo->create($i->code . "-1.png", FileType::Image, $i->id, $item->photo_1,
                'image/png');
            $lo->unlock($file);
            $i->update(['photo_2' => $file->id]);
        }

        if ($item->photo_2)
        {
            $lo = new LoFileHandler();
            $file = $lo->create($i->code . "-2.png", FileType::Image, $i->id, $item->photo_2,
                'image/png');
            $lo->unlock($file);
            $i->update(['photo_3' => $file->id]);
        }

        if ($item->photo_3)
        {
            $lo = new LoFileHandler();
            $file = $lo->create($i->code . "-3.png", FileType::Image, $i->id, $item->photo_3,
                'image/png');
            $lo->unlock($file);
            $i->update(['photo_4' => $file->id]);
        }

        if ($item->photo_4)
        {
            $lo = new LoFileHandler();
            $file = $lo->create($i->code . "-4.png", FileType::Image, $i->id, $item->photo_4,
                'image/png');
            $lo->unlock($file);
            $i->update(['photo_5' => $file->id]);
        }

        if ($item->slick_id)
        {
            $lo = new LoFileHandler();
            $file = $lo->create($i->code . ".pdf", FileType::Document, $i->id, $item->slick_id,
                'application/pdf');
            $lo->unlock($file);
            $i->update(['slick_id' => $file->id]);
        }

        // Now we need to process tags
        $tagList = explode("\n", $item->tag_list);
        $cat = BillCategory::find($request->category_id);
        foreach ($tagList as $t)
        {
            $x = explode(":", $t);
            if (!isset($x[1])) continue;
            $sCat = trim($x[0]);
            $sTag = trim($x[1]);
            $tcat = TagCategory::where('bill_category_id', $cat->id)->where('name', $sCat)->first();
            if (!$tcat)
            {
                $tcat = TagCategory::create(['bill_category_id' => $cat->id, 'name' => $sCat, 'description' => '']);
            }
            $ttag = Tag::where('tag_category_id', $tcat->id)->where('name', $sTag)->first();
            if (!$ttag)
            {
                $ttag = $tcat->tags()->create([
                    'name'        => $sTag,
                    'description' => ''
                ]);
            }
            $i->refresh();

            $i->tags()->create(['tag_id' => $ttag->id]);
        }
        return redirect()->to("/admin/category/$request->category_id/items/$i->id");
    }

    /**
     * Show create form for new product/service
     * @param BillCategory $cat
     * @param BillItem     $item
     * @return View
     */
    public function show(BillCategory $cat, BillItem $item): View
    {
        $type = Str::singular($cat->type);
        return view("admin.bill_items.$type", [
            'cat'    => $cat,
            'item'   => $item,
            'type'   => $type,
            'crumbs' => $this->generateCrumbs($cat, $item)
        ]);
    }

    /**
     * Store a new product or service.
     * @param BillCategory $cat
     * @param Request      $request
     * @return RedirectResponse
     * @throws LogicException
     */
    public function store(BillCategory $cat, Request $request): RedirectResponse
    {
        $request->validate([
            'code'        => 'required',
            'name'        => 'required',
            'description' => 'required'
        ]);
        $item = (new BillItem)->create([
            'bill_category_id'      => $cat->id,
            'code'                  => $request->code,
            'name'                  => $request->name,
            'type'                  => $cat->type,
            'description'           => $request->description,
            'nrc'                   => convertMoney($request->nrc),
            'mrc'                   => convertMoney($request->mrc),
            'msrp'                  => convertMoney($request->msrp),
            'ex_capex'              => convertMoney($request->ex_capex),
            'ex_capex_description'  => $request->ex_capex_description,
            'ex_capex_once'         => $request->ex_capex_once,
            'ex_opex'               => convertMoney($request->ex_opex),
            'ex_opex_description'   => $request->ex_opex_description,
            'ex_opex_once'          => $request->ex_opex_once,
            'ex_opexfreq'           => $request->ex_opexfreq,
            'feature_headline'      => $request->feature_headline,
            'feature_list'          => $request->feature_list,
            'feature_priority'      => $request->feature_priority,
            'is_shipped'            => $request->is_shipped ? 1 : 0,
            'allowed_qty'           => $request->allowed_qty,
            'allowed_type'          => $request->allowed_type,
            'allowed_overage'       => $request->allowed_overage,
            'shop_show'             => $request->shop_show,
            'slug'                  => Str::slug($request->name),
            'marketing_description' => $request->marketing_description,
            'tos_id'                => $request->tos_id,
            'on_hand'               => $request->on_hand,
            'track_qty'             => $request->track_qty,
            'allow_backorder'       => $request->allow_backorder,
            'msrp_note'             => $request->msrp_note,
            'reservation_mode'      => (bool)$request->reservation_mode,
            'reservation_price'     => $request->reservation_price,
            'reservation_details'   => $request->reservation_details,
            'reservation_time'      => $request->reservation_time,
            'reservation_refund'    => $request->reservation_refund,
            'min_price'             => convertMoney($request->min_price),
            'max_price'             => convertMoney($request->max_price),
        ]);
        if ($item->parent_id)
        {
            $item->update(['variation_name' => $request->variation_name]);
        }
        else
        {
            $item->update([
                'variation_name'     => $request->variation_name,
                'variation_category' => $request->variation_category
            ]);
        }

        foreach ($request->all() as $key => $val)
        {
            if (preg_match("/sterm_/i", $key))
            {
                $x = explode("sterm_", $key);
                $key = $x[1];
                $item->changeDiscountTerm((int)$key, (float)$val);
            }
        }
        $this->handleFiles($item, $request);
        return redirect()->to("/admin/category/$cat->id/items")->with('message', "$item->name created.");
    }

    /**
     * Update Item
     * @param BillCategory $cat
     * @param BillItem     $item
     * @param Request      $request
     * @return RedirectResponse
     * @throws LogicException
     */
    public function update(BillCategory $cat, BillItem $item, Request $request): RedirectResponse
    {
        if ($request->assign == 'tag')
        {
            $item->tags()->create([
                'tag_id' => $request->tag
            ]);
            return redirect()->back();
        }
        $request->validate([
            'code'        => 'required',
            'name'        => 'required',
            'description' => 'required'
        ]);
        $request->merge(['code' => strtoupper(Str::slug($request->code))]);
        $item->update([
            'bill_category_id'      => $cat->id,
            'code'                  => $request->code,
            'name'                  => $request->name,
            'type'                  => $cat->type,
            'description'           => $request->description,
            'nrc'                   => convertMoney($request->nrc),
            'mrc'                   => convertMoney($request->mrc),
            'msrp'                  => convertMoney($request->msrp),
            'ex_capex'              => convertMoney($request->ex_capex),
            'ex_capex_description'  => $request->ex_capex_description,
            'ex_capex_once'         => $request->ex_capex_once,
            'ex_opex'               => convertMoney($request->ex_opex),
            'ex_opex_description'   => $request->ex_opex_description,
            'ex_opex_once'          => $request->ex_opex_once,
            'ex_opexfreq'           => $request->ex_opexfreq,
            'feature_headline'      => $request->feature_headline,
            'feature_list'          => $request->feature_list,
            'feature_priority'      => $request->feature_priority,
            'is_shipped'            => $request->is_shipped ? 1 : 0,
            'allowed_qty'           => $request->allowed_qty,
            'allowed_type'          => $request->allowed_type,
            'allowed_overage'       => $request->allowed_overage,
            'frequency'             => $request->frequency,
            'shop_show'             => $request->shop_show,
            'slug'                  => Str::slug($request->name),
            'tos_id'                => $request->tos_id,
            'marketing_description' => $request->marketing_description,
            'on_hand'               => $request->on_hand,
            'track_qty'             => $request->track_qty,
            'allow_backorder'       => $request->allow_backorder,
            'msrp_note'             => $request->msrp_note,
            'reservation_mode'      => (bool)$request->reservation_mode,
            'reservation_price'     => $request->reservation_price,
            'reservation_details'   => $request->reservation_details,
            'reservation_time'      => $request->reservation_time,
            'reservation_refund'    => $request->reservation_refund,
            'min_price'             => convertMoney($request->min_price),
            'max_price'             => convertMoney($request->max_price),
        ]);
        if ($item->parent_id)
        {
            $item->update(['variation_name' => $request->variation_name]);
        }
        else
        {
            $item->update([
                'variation_name'     => $request->variation_name,
                'variation_category' => $request->variation_category
            ]);

        }
        foreach ($request->all() as $key => $val)
        {
            if (preg_match("/sterm_/i", $key))
            {
                $x = explode("sterm_", $key);
                $key = $x[1];
                $item->changeDiscountTerm((int)$key, (float)$val);
            }
        }
        $this->handleFiles($item, $request);
        return redirect()->to("/admin/category/$cat->id/items")->with('message', "$item->name updated.");
    }

    /**
     * Remove a bill item
     * @param BillCategory $cat
     * @param BillItem     $item
     * @return string[]
     */
    public function destroyItem(BillCategory $cat, BillItem $item): array
    {
        // Remove photos first.
        $lo = new LoFileHandler();
        if ($item->photo_id && !$item->parent_id)
        {
            $lo->delete($item->photo_id);
        }
        if ($item->slick_id && !$item->parent_id)
        {
            $lo->delete($item->slick_id);
        }
        $item->tags()->delete();
        $item->delete();
        return ['callback' => "redirect:/admin/category/$cat->id/items"];
    }

    /**
     * Remove assigned tag from item.
     * @param BillCategory $cat
     * @param BillItem     $item
     * @param BillItemTag  $tag
     * @return RedirectResponse
     */
    public function removeTag(BillCategory $cat, BillItem $item, BillItemTag $tag): RedirectResponse
    {
        $item->tags()->where('id', $tag->id)->delete();
        return redirect()->back();
    }

    /**
     * Show create group modal.
     * @param BillCategory $cat
     * @param BillItem     $item
     * @return View
     */
    public function createGroupModal(BillCategory $cat, BillItem $item): View
    {
        return view('admin.bill_items.addon_create')->with(['cat' => $cat, 'item' => $item, 'addon' => new Addon]);
    }

    /**
     * Create a new addon group for an item.
     * @param BillCategory $cat
     * @param BillItem     $item
     * @param Request      $request
     * @return RedirectResponse
     */
    public function createGroup(BillCategory $cat, BillItem $item, Request $request): RedirectResponse
    {
        $request->validate([
            'name' => "required"
        ]);
        $item->addons()->create([
            'name'        => $request->name,
            'description' => $request->description
        ]);
        return redirect()->back()->with('message', $request->name . " created successfully.");
    }

    /**
     * Show update addon group modal
     * @param BillCategory $cat
     * @param BillItem     $item
     * @param Addon        $addon
     * @return View
     */
    public function updateGroupModal(BillCategory $cat, BillItem $item, Addon $addon): View
    {
        return view('admin.bill_items.addon_create')->with(['cat' => $cat, 'item' => $item, 'addon' => $addon]);
    }

    /**
     * Update an addon group
     * @param BillCategory $cat
     * @param BillItem     $item
     * @param Addon        $addon
     * @param Request      $request
     * @return RedirectResponse
     */
    public function updateGroup(BillCategory $cat, BillItem $item, Addon $addon, Request $request): RedirectResponse
    {
        $request->validate([
            'name' => "required"
        ]);
        $addon->update($request->all());
        return redirect()->back()->with('message', $addon->name . " updated successfully.");
    }

    /**
     * Add a new option to an addon
     * @param BillCategory $cat
     * @param BillItem     $item
     * @param Addon        $addon
     * @return View
     */
    public function addOptionModal(BillCategory $cat, BillItem $item, Addon $addon): View
    {
        return view('admin.bill_items.option_create')->with([
            'cat'    => $cat,
            'item'   => $item,
            'addon'  => $addon,
            'option' => new AddonOption()
        ]);
    }

    /**
     * Add a new option to an addon
     * @param BillCategory $cat
     * @param BillItem     $item
     * @param Addon        $addon
     * @param AddonOption  $option
     * @return View
     */
    public function showOption(BillCategory $cat, BillItem $item, Addon $addon, AddonOption $option): View
    {

        return view('admin.bill_items.option_create')->with([
            'cat'    => $cat,
            'item'   => $item,
            'addon'  => $addon,
            'option' => $option
        ]);
    }

    /**
     * Store a new option
     * @param BillCategory $cat
     * @param BillItem     $item
     * @param Addon        $addon
     * @param Request      $request
     * @return RedirectResponse
     */
    public function storeOption(BillCategory $cat, BillItem $item, Addon $addon, Request $request): RedirectResponse
    {
        if (!$request->name && !$request->bill_item_id) throw new \LogicException("You must specify a name OR an item.");
        $i = $request->bill_item_id ? BillItem::find($request->bill_item_id) : null;
        if ($i && !$request->price)
        {
            $request->merge(['price' => $i->type == BillItemType::PRODUCT->value ? $i->nrc : $i->mrc]);
        }
        if ($i && !$request->name)
        {
            $request->merge(['name' => $i->name]);
        }
        $addon->options()->create([
            'name'         => $request->name,
            'bill_item_id' => $request->bill_item_id,
            'price'        => convertMoney($request->price),
            'notes'        => $request->notes,
            'max'          => $request->max ?: 1
        ]);
        return redirect()->back();
    }

    /**
     * Store a new option
     * @param BillCategory $cat
     * @param BillItem     $item
     * @param Addon        $addon
     * @param AddonOption  $option
     * @param Request      $request
     * @return RedirectResponse
     */
    public function updateOption(
        BillCategory $cat,
        BillItem $item,
        Addon $addon,
        AddonOption $option,
        Request $request
    ): RedirectResponse {
        if (!$request->name && !$request->bill_item_id) throw new \LogicException("You must specify a name OR an item.");
        $i = $request->bill_item_id ? BillItem::find($request->bill_item_id) : null;
        if ($i && !$request->price)
        {
            $request->merge(['price' => $i->type == BillItemType::PRODUCT->value ? $i->nrc : $i->mrc]);
        }
        if ($i && !$request->name)
        {
            $request->merge(['name' => $i->name]);
        }
        $option->update([
            'name'         => $request->name,
            'bill_item_id' => $request->bill_item_id,
            'price'        => convertMoney($request->price),
            'notes'        => $request->notes,
            'max'          => $request->max ?: 1
        ]);
        return redirect()->back();
    }

    /**
     * Remove an addon option for an item.
     * @param BillCategory $cat
     * @param BillItem     $item
     * @param Addon        $addon
     * @param AddonOption  $option
     * @return string[]
     */
    public function deleteOption(BillCategory $cat, BillItem $item, Addon $addon, AddonOption $option): array
    {
        $option->delete();
        return ['callback' => 'reload', 'message' => "Option removed successfully."];
    }

    /**
     * Handle uploaded files for a bill item.
     * @param BillItem $item
     * @param Request  $request
     * @return void
     * @throws LogicException
     */
    private function handleFiles(BillItem $item, Request $request): void
    {
        $photos = ['photo_id', 'photo_2', 'photo_3', 'photo_4', 'photo_5'];
        foreach ($photos as $key)
        {
            if ($request->hasFile($key))
            {
                $lo = new LoFileHandler();
                if ($item->{$key})
                {
                    $lo->delete($item->{$key});
                }
                $file = $lo->createFromRequest($request, $key, FileType::Image, $item->id);
                $lo->unlock($file);
                $item->update([$key => $file->id]);
            }
        }

        if ($request->hasFile('slick_id'))
        {
            $lo = new LoFileHandler();
            if ($item->slick_id)
            {
                $lo->delete($item->slick_id);
            }
            $file = $lo->createFromRequest($request, 'slick_id', FileType::Slick, $item->id);
            $lo->unlock($file);
            $item->update(['slick_id' => $file->id]);
        }
    }

    /**
     * Show add variation modal
     * @param BillCategory $cat
     * @param BillItem     $item
     * @return View
     */
    public function variationModal(BillCategory $cat, BillItem $item): View
    {
        return view('admin.bill_items.variation_modal', ['category' => $cat, 'item' => $item]);
    }

    /**
     * Create a variation of an item.
     * @param BillCategory $cat
     * @param BillItem     $item
     * @param Request      $request
     * @return RedirectResponse
     * @throws LogicException
     */
    public function createVariation(BillCategory $cat, BillItem $item, Request $request): RedirectResponse
    {
        if (BillItem::where('code', $request->code)->count())
        {
            throw new LogicException("Item Codes must be unique.");
        }
        $new = $item->replicate();

        $new->parent_id = $item->id;
        $new->name = $request->name;
        $new->code = $request->code;
        $new->save();
        $new->refresh();
        if ($request->copy_tags)
        {
            foreach ($item->tags as $tag)
            {
                $new->tags()->create([
                    'tag_id' => $tag->tag_id
                ]);
            }
        } // copy tags
        if ($request->copy_addons)
        {
            foreach ($item->addons as $addon)
            {
                $na = $new->addons()->create([
                    'name'        => $addon->name,
                    'description' => $addon->description
                ]);
                foreach ($addon->options as $opt)
                {
                    $na->options()->create([
                        'addon_id'     => $na->id,
                        'bill_item_id' => $opt->bill_item_id,
                        'price'        => $opt->price,
                        'notes'        => $opt->notes,
                        'max'          => $opt->max
                    ]);
                }
            } // fe addon
        } // if copy tags

        // If we need to copy photos, we need to COPY them, not take in place of.
        // This fixes a condition where we update a different id and breaks the original.
        // If we are not copying the photos then set them all to null - don't overwrite originals.
        $iids = ['photo_id', 'photo_2', 'photo_3', 'photo_4', 'photo_5'];
        if ($request->copy_photos)
        {
            foreach ($iids as $id)
            {
                if ($item->{$id}) // If blank ignore.
                {
                    $handler = new LoFileHandler();
                    $newFile = $handler->duplicate($item->{$id}, $new->id);
                    if ($newFile)
                    {
                        $handler->unlock($newFile);
                        $new->update([$id => $newFile->id]);
                    }
                }
            }
        }
        else
        {
            foreach ($iids as $id)
            {
                $new->update([$id => null]);
            }
        }

        // Copy Data requirements
        if ($request->copy_requirements)
        {
            foreach ($item->meta as $meta)
            {
                $new->meta()->create([
                    'bill_item_id'      => $new->id,
                    'item'              => $meta->item,
                    'answer_type'       => $meta->answer_type,
                    'opts'              => $meta->opts,
                    'required_sale'     => $meta->required_sale,
                    'per_qty'           => $meta->per_qty,
                    'customer_viewable' => $meta->customer_viewable,
                    'description'       => $meta->description
                ]);
            }


        }


        return redirect()->to("/admin/category/$cat->id/items/$new->id");
    }

    /**
     * Add new Metadata/Requirement
     * @param BillCategory $cat
     * @param BillItem     $item
     * @return View
     */
    public function addMeta(BillCategory $cat, BillItem $item): View
    {
        return view('admin.bill_items.requirement_modal', [
            'cat'  => $cat,
            'item' => $item,
            'meta' => new BillItemMeta()
        ]);
    }

    /**
     * Show edit requirement form
     * @param BillCategory $cat
     * @param BillItem     $item
     * @param BillItemMeta $meta
     * @return View
     */
    public function editMeta(BillCategory $cat, BillItem $item, BillItemMeta $meta): View
    {
        return view('admin.bill_items.requirement_modal', [
            'cat'  => $cat,
            'item' => $item,
            'meta' => $meta
        ]);
    }

    /**
     * Save new data requirement
     * @param BillCategory $cat
     * @param BillItem     $item
     * @param Request      $request
     * @return RedirectResponse
     */
    public function saveMeta(BillCategory $cat, BillItem $item, Request $request): RedirectResponse
    {
        $request->validate(['item' => 'required', 'answer_type' => 'required']);
        $item->meta()->create($request->all());
        return redirect()->back()->with('message', "Data Requirement created successfully.");
    }

    /**
     * Update data requirement
     * @param BillCategory $cat
     * @param BillItem     $item
     * @param BillItemMeta $meta
     * @param Request      $request
     * @return RedirectResponse
     */
    public function updateMeta(
        BillCategory $cat,
        BillItem $item,
        BillItemMeta $meta,
        Request $request
    ): RedirectResponse {

        $request->validate(['item' => 'required', 'answer_type' => 'required']);
        $meta->update($request->all());
        return redirect()->back()->with('message', "Data Requirement updated successfully.");
    }

    /**
     * Remove Item Meta
     * @param BillCategory $cat
     * @param BillItem     $item
     * @param BillItemMeta $meta
     * @return string[]
     */
    public function removeMeta(BillCategory $cat, BillItem $item, BillItemMeta $meta): array
    {
        $meta->delete();
        return ['callback' => 'reload'];
    }

    /**
     * Remove Addon
     * @param BillCategory $cat
     * @param BillItem     $item
     * @param Addon        $addon
     * @return string[]
     */
    public function removeAddon(BillCategory $cat, BillItem $item, Addon $addon): array
    {
        AccountAddon::where('addon_id', $addon->id)->delete();
        QuoteItemAddon::where('addon_id', $addon->id)->delete();
        $addon->delete();
        return ['callback' => "reload"];
    }

    /**
     * Show create FAQ modal
     * @param BillCategory $cat
     * @param BillItem     $item
     * @return View
     */
    public function createFaqModal(BillCategory $cat, BillItem $item): View
    {
        return view('admin.bill_items.faq_modal', ['cat' => $cat, 'item' => $item, 'faq' => new BillItemFaq]);
    }

    /**
     * Store a new faq for an item.
     * @param BillCategory $cat
     * @param BillItem     $item
     * @param Request      $request
     * @return RedirectResponse
     */
    public function storeFaq(BillCategory $cat, BillItem $item, Request $request): RedirectResponse
    {
        $request->validate([
            'question' => 'required',
            'answer'   => 'required'
        ]);
        $item->faqs()->create([
            'question' => $request->question,
            'answer'   => $request->answer
        ]);
        return redirect()->back()->with('message', "FAQ added successfully.");
    }

    /**
     * Show edit FAQ modal
     * @param BillCategory $cat
     * @param BillItem     $item
     * @param BillItemFaq  $faq
     * @return View
     */
    public function showFaqModal(BillCategory $cat, BillItem $item, BillItemFaq $faq): View
    {
        return view('admin.bill_items.faq_modal', ['cat' => $cat, 'item' => $item, 'faq' => $faq]);
    }

    /**
     * Update a FAQ Entry
     * @param BillCategory $cat
     * @param BillItem     $item
     * @param BillItemFaq  $faq
     * @param Request      $request
     * @return RedirectResponse
     */
    public function updateFaq(BillCategory $cat, BillItem $item, BillItemFaq $faq, Request $request): RedirectResponse
    {
        $request->validate([
            'question' => 'required',
            'answer'   => 'required'
        ]);
        $faq->update([
            'question' => $request->question,
            'answer'   => $request->answer
        ]);
        return redirect()->back()->with('message', "FAQ updated successfully.");
    }

    /**
     * Remove a FAQ Entry
     * @param BillCategory $cat
     * @param BillItem     $item
     * @param BillItemFaq  $faq
     * @return array
     */
    public function deleteFaq(BillCategory $cat, BillItem $item, BillItemFaq $faq): array
    {
        $faq->delete();
        return ['callback' => 'reload'];
    }

    /**
     * Show Category Change Modal
     * @param BillCategory $cat
     * @param BillItem     $item
     * @return View
     */
    public function categoryModal(BillCategory $cat, BillItem $item): View
    {
        return view('admin.bill_items.category_modal', ['category' => $cat, 'item' => $item]);
    }

    /**
     * Update Category
     * @param BillCategory $cat
     * @param BillItem     $item
     * @param Request      $request
     * @return RedirectResponse
     */
    public function changeCategory(BillCategory $cat, BillItem $item, Request $request)
    {
        $item->update(['bill_category_id' => $request->category_id]);
        return redirect()->back()->with('message', 'Category Updated Successfully');
    }

    /**
     * Show AI Generation Component
     * @param BillCategory $cat
     * @param BillItem     $item
     * @return View
     */
    public function marketing(BillCategory $cat, BillItem $item): View
    {
        $crumbs = $this->generateCrumbs($cat, $item);
        return view('admin.bill_items.generator', ['item' => $item, 'crumbs' => $crumbs]);
    }

}

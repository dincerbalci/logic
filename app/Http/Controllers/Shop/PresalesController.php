<?php

namespace App\Http\Controllers\Shop;

use App\Exceptions\LogicException;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

class PresalesController extends Controller
{

    /**
     * Show presales dashboard.
     * @param string $slug
     * @return View
     */
    public function index(string $slug): View
    {
        $lead = Lead::where('hash', $slug)->first();
        if (!$lead) abort(404);
        if (!$lead->active) abort(404);
        seo()->title("Welcome $lead->company");
        return view('shop.presales.index', ['lead' => $lead]);
    }

    /**
     * Show Quote Cart
     * @param string $slug
     * @param string $qslug
     * @return View
     */
    public function quote(string $slug, string $qslug): View
    {
        $lead = Lead::where('hash', $slug)->first();
        if (!$lead) abort(404);
        if (!$lead->active) abort(404);
        $quote = $lead->quotes()->where('hash', $qslug)->first();
        if (!$quote) abort(404);
        if ($quote->archived) abort(404);
        return view('shop.presales.quote.index', ['lead' => $lead, 'quote' => $quote]);
    }

    /**
     * Show contact information editor
     * @param string $slug
     * @return View
     */
    public function contactModal(string $slug): View
    {
        $lead = Lead::where('hash', $slug)->first();
        if (!$lead) abort(404);
        if (!$lead->active) abort(404);
        return view('shop.presales.profile_modal', ['lead' => $lead]);
    }

    /**
     * Update contact information
     * @param string  $slug
     * @param Request $request
     * @return RedirectResponse
     * @throws LogicException
     */
    public function saveContact(string $slug, Request $request): RedirectResponse
    {
        $lead = Lead::where('hash', $slug)->first();
        if (!$lead) abort(404);
        if (!$lead->active) abort(404);
        if (!filter_var($request->email, FILTER_VALIDATE_EMAIL))
        {
            throw new LogicException("A valid email address is required.");
        }

        if (User::where('email', $request->email)->count())
        {
            throw new LogicException("This email address already exists. Please use another email address.");
        }
        $request->validate([
            'company' => 'required',
            'contact' => 'required',
            'address' => 'required',
            'city'    => "required",
            'state'   => 'required',
            'zip'     => "required",
            'phone'   => 'required'
        ]);
        $lead->update([
            'company'  => $request->company,
            'contact'  => $request->contact,
            'email'    => $request->email,
            'phone'    => $request->phone,
            'address'  => $request->address,
            'address2' => $request->address2,
            'city'     => $request->city,
            'state'    => $request->state,
            'zip'      => $request->zip
        ]);
        return redirect()->back();

    }

    /**
     * Show discovery questionnaire modal.
     * @param string $slug
     * @return View
     */
    public function questionModal(string $slug): View
    {
        $lead = Lead::where('hash', $slug)->first();
        if (!$lead) abort(404);
        if (!$lead->active) abort(404);
        return view('shop.presales.question_modal', ['lead' => $lead]);
    }

    /**
     * Update Questionnaire
     * @param string  $slug
     * @param Request $request
     * @return RedirectResponse
     */
    public function saveQuestions(string $slug, Request $request) : RedirectResponse
    {
        $lead = Lead::where('hash', $slug)->first();
        if (!$lead) abort(404);
        if (!$lead->active) abort(404);

        foreach ($request->all() as $key => $val)
        {
            if (str_contains($key, "d_"))
            {
                $key = explode("_", $key);
                $key = $key[1]; // d_1 gives 1
                $record = $lead->discoveries()->where('discovery_id', $key)->firstOrCreate(['discovery_id' => $key]);
                $record->update(['value' => $val]);
            }
        }
        return redirect()->back();
    }


}

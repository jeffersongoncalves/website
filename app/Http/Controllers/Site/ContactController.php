<?php

namespace App\Http\Controllers\Site;

use App\Http\Requests\Site\ContactRequest;
use App\Mail\ContactMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class ContactController
{
    public function show(): View
    {
        return view('site.contact', [
            'kinds'  => config('site.contact_kinds'),
            'budget' => config('site.budget_options'),
        ]);
    }

    public function submit(ContactRequest $request): RedirectResponse
    {
        $payload = array_merge($request->validated(), [
            'locale' => app()->getLocale(),
        ]);

        Mail::to(config('site.social.email'))->send(new ContactMessage($payload));

        return redirect()
            ->route('contact', ['locale' => app()->getLocale()])
            ->with('contact_sent', true);
    }
}

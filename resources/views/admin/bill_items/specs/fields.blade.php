<div class="card">
    <div class="card-body">
        <h6 class="card-title">Product/Service Definitions</h6>
        <p class="card-text">When listing your product or service, you will need to create some basic information like
            the SKU, name, and a basic quote/invoice definition.</p>
        <form method="POST" action="/admin/category/{{$cat->id}}/items/{{$item->id}}/specs">
            @method('PUT')
            @csrf

            <div class="row g-2 mb-2 mt-2">
                <div class="col-md-4 col-4">
                    <div class="form-floating">
                        <input type="text" class="form-control" name="code" value="{{$item->code}}">
                        <label>{{ucfirst($type)}} Code</label>
                        <span
                            class="helper-text">Enter a code to define this item.</span>
                    </div>
                </div>
                <div class="col-md-4 col-4">
                    <div class="form-floating">
                        <input type="text" class="form-control" name="name" value="{{$item->name}}">
                        <label>{{ucfirst($type)}} Name</label>
                        <span class="helper-text">Enter name to be used on invoice/quote.</span>
                    </div>
                </div>

                @if($item->type == 'services')
                    <div class="col-md-4 col-4">
                        <div class="form-floating">
                            {!! Form::select('tos_id', array_replace([0 => '-- No Terms of Service --'], \App\Models\Term::all()->pluck("name", "id")->all()), $item->tos_id, ['class' => 'form-select']) !!}
                            <label>Terms of Service (if applicable)</label>
                            <span class="helper-text">If this item is sold should customer be required to agree to a terms of service?</span>
                        </div>
                    </div>
                @endif

            </div> <!-- .row end -->

            <div class="row g-2">
                <div class="col-md-8 col-8">
                    <div class="form-floating">
                                        <textarea class="form-control" style="height:140px;"
                                                  name="description">{!! $item->description !!}</textarea>
                        <label>{{ucfirst($type)}} Description (Invoice/Quote) </label>
                        <span class="helper-text">Enter the description to be used on quotes and invoices</span>
                    </div>
                </div>
            </div>

            @if($item->type == 'products')
                <div class="row g-2 mt-3">
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_shipped" value="1"
                                   id="shipped" {{$item->is_shipped ? "checked" : null}}>
                            <label class="form-check-label" for="shipped">Item is Shipped to
                                Customer</label>
                        </div>
                    </div>


                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="track_qty" value="1"
                                   id="track_qty" {{$item->track_qty ? "checked" : null}}>
                            <label class="form-check-label" for="track_qty">Track Inventory Quantity when
                                Sold?</label>
                        </div>
                    </div>


                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="allow_backorder" value="1"
                                   id="allow_backorder" {{$item->allow_backorder ? "checked" : null}}>
                            <label class="form-check-label" for="allow_backorder">Allow backorder if out of
                                stock?</label>
                        </div>
                    </div>
                </div>
            @endif

            <div class="row mt-3">
                <div class="col-xl-6">
                    <input type="submit" class="btn btn-outline-primary wait" data-message="Updating Definitions.."
                           value="Save and Continue">
                </div>
            </div>
        </form>

    </div>
</div>

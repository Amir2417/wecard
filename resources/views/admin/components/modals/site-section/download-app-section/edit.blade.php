<div id="download-app-edit" class="mfp-hide large">
    <div class="modal-data">
        <div class="modal-header px-0">
            <h5 class="modal-title">{{ __("Edit Item") }}</h5>
        </div>
        <div class="modal-form-data">
            <form class="modal-form" method="POST" action="{{ setRoute('admin.setup.sections.section.item.update',$slug) }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="target" value="{{ old('target') }}">
                <div class="row mb-10-none mt-3">
                    <div class="language-tab">
                        <nav>
                            <div class="nav nav-tabs" id="nav-tab" role="tablist">
                                <button class="nav-link @if (get_default_language_code() == language_const()::NOT_REMOVABLE) active @endif" id="edit-modal-english-tab" data-bs-toggle="tab" data-bs-target="#edit-modal-english" type="button" role="tab" aria-controls="edit-modal-english" aria-selected="false">English</button>
                                @foreach ($languages as $item)
                                    <button class="nav-link @if (get_default_language_code() == $item->code) active @endif" id="edit-modal-{{$item->name}}-tab" data-bs-toggle="tab" data-bs-target="#edit-modal-{{$item->name}}" type="button" role="tab" aria-controls="edit-modal-{{ $item->name }}" aria-selected="true">{{ $item->name }}</button>
                                @endforeach

                            </div>
                        </nav>
                        <div class="tab-content" id="nav-tabContent">
                            <div class="tab-pane @if (get_default_language_code() == language_const()::NOT_REMOVABLE) fade show active @endif" id="edit-modal-english" role="tabpanel" aria-labelledby="edit-modal-english-tab">
                                <div class="form-group">
                                    @include('admin.components.form.input',[
                                        'label'     => "Title*",
                                        'name'      => $default_lang_code . "_item_title_edit",
                                        'value'     => old($default_lang_code . "_item_title_edit",$data->value->language->$default_lang_code->item_title ?? "")
                                    ])
                                </div>
                                <div class="form-group">
                                    @include('admin.components.form.input',[
                                        'label'     => "Heading*",
                                        'name'      => $default_lang_code . "_item_heading_edit",
                                        'value'     => old($default_lang_code . "_item_heading_edit",$data->value->language->$default_lang_code->item_heading ?? "")
                                    ])
                                </div>
                            </div>
                            @foreach ($languages as $item)
                                @php
                                    $lang_code = $item->code;
                                @endphp
                                <div class="tab-pane @if (get_default_language_code() == $item->code) fade show active @endif" id="edit-modal-{{ $item->name }}" role="tabpanel" aria-labelledby="edit-modal-{{$item->name}}-tab">
                                    <div class="form-group">
                                        @include('admin.components.form.input',[
                                            'label'     => "Title*",
                                            'name'      => $lang_code . "_item_title_edit",
                                            'value'     => old($lang_code . "_item_title_edit",$data->value->language->$lang_code->item_title ?? "")
                                        ])
                                    </div>
                                    <div class="form-group">
                                        @include('admin.components.form.input',[
                                            'label'     => "Heading*",
                                            'name'      => $lang_code . "_item_heading_edit",
                                            'value'     => old($lang_code . "_item_heading_edit",$data->value->language->$lang_code->item_heading ?? "")
                                        ])
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="form-group">
                        @include('admin.components.form.input',[
                            'label'     => "Link*",
                            'name'      => "link",
                            'value'     => old("link")
                        ])
                    </div>
                    <div class="col-xl-12 col-lg-12 form-group">
                        @include('admin.components.form.input-file',[
                            'label'             => "Image:",
                            'name'              => "image",
                            'class'             => "file-holder",
                            'old_files_path'    => files_asset_path("site-section"),
                            'old_files'         => old("old_image"),
                        ])
                    </div>
                    <div class="col-xl-12 col-lg-12 form-group d-flex align-items-center justify-content-between mt-4">
                        <button type="button" class="btn btn--danger modal-close">{{ __("Cancel") }}</button>
                        <button type="submit" class="btn btn--base">{{ __("Update") }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
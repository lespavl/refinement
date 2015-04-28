@foreach($refinement_categories as $category)
    <div class="col-md-4 refinementBox">
        <h3 class="refinementTitle">{{ $category['title'] }}</h3>
        <div class="refinementBoxIn">
            @foreach($category['options'] as $option)
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name='{{ $category['parent_table'] }}[{{ $category['column_name'] }}][]'
                               value='{{ $option['id'] }}' @if($option['checked']) checked @endif />
                        {{ $option['name'] }} ({{ $option['count'] }})
                    </label>
                </div>
            @endforeach
        </div>
    </div>
@endforeach
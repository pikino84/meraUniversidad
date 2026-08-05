<div
    class="mt-2"
    style="margin-left: {{ $level * 30 }}px;">


    <div class="card mera-tree-card">


        <div class="card-body py-2">


            <div class="d-flex justify-content-between align-items-center">


                <div class="d-flex align-items-center">


                    @if($category->childrenRecursive->count())

                    <i class="fa fa-folder mera-folder-icon mr-2"></i>

                    @else

                    <i class="fa fa-folder-open mera-folder-empty-icon mr-2"></i>

                    @endif


                    <div>

                        <strong class="mera-tree-title">
                            {{ $category->name }}
                        </strong>


                        <small class="mera-tree-count ml-2">
                            ({{ $category->childrenRecursive->count() }} subcategorías)
                        </small>

                    </div>


                </div>



                <div class="btn-group">


                    <a
                        href="{{ route('categories.create',['parent'=>$category->id]) }}"
                        class="mera-action-btn mera-add-btn"
                        title="Nueva subcategoría">

                        <i class="fa fa-plus"></i>

                    </a>



                    <a
                        href="{{ route('categories.edit',$category) }}"
                        class="mera-action-btn mera-edit-btn"
                        title="Editar">

                        <i class="fas fa-pencil-alt"></i>

                    </a>



                    <form
                        action="{{ route('categories.destroy',$category) }}"
                        method="POST"
                        id="delete-form-{{ $category->id }}"
                        class="d-inline">

                        @csrf
                        @method('DELETE')


                        <button
                            type="button"
                            class="mera-action-btn mera-delete-btn"
                            onclick="confirmDelete({{ $category->id }})"
                            title="Eliminar">

                            <i class="fa fa-trash"></i>

                        </button>


                    </form>


                </div>


            </div>


        </div>


    </div>



    @foreach($category->childrenRecursive as $child)

    @include(
    'categories.components.tree-item',
    [
    'category'=>$child,
    'level'=>$level+1
    ]
    )

    @endforeach


</div>
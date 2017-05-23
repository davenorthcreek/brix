@extends('admin_template')

@section('content')

    <div class='row'>
        <div class='col-md-9'>
            <?PHP $id = $candidate->get('id'); ?>
            @if (count($errors) > 0)
                <div class="alert alert-danger">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if(isset($errormessage))
                <div class="panel panel-danger">
                    <div class="panel-heading">{{ $errormessage['message'] }}</div>
                    <div class="panel-body">
                        @foreach ($errormessage['errors'] as $error)
                            Property:&nbsp;<strong>{{$error['propertyName'] }}</strong><br>
                            Value:&nbsp;&nbsp;&nbsp;<strong>{{$candidate->get_a_string($candidate->get($error['propertyName'])) }}</strong><br>
                            Severity:&nbsp;<strong>{{$error['severity'] }}</strong><br>
                            Issue:&nbsp;&nbsp;&nbsp;<strong>{{$error['type'] }}</strong><br><hr>
                        @endforeach
                    </div>
                </div>
            @endif
            <form method="post" enctype="multipart/form-data" id="confirmValues" action='{{route("confirmValues", ["source" => $source])}}' >
                <input type='hidden' name='source' value="{{$source}}">
            <div class="box box-{{$box_style}}">
                <div class="box-header with-border">
                    <h1 class='box-title' style='font-weight:bold'>{{ $page_title or ""}}</h1>
                    <button class="btn btn-mini btn-{{$box_style}} pull-right btn-click-action masterbutton"
                        data-widget="collapseAll" data-toggle="tooltip" title="Collapse/Expand All">
                        <i class='fa fa-plus'></i>
                    </button>
                </div>
            </div>
            <?php
                $sections = $form->get("sections");
                $headers = $form->get("sectionHeaders");
            ?>
            @for ($i = 0; $i < count($sections); $i++)
                <?php
                $section = $sections[$i];
                $label = $headers[$i];
                ?>
                @if ($label == 'Personal Details' || $label == 'Professional Details')
                    <div class="box box-{{$box_style}} ">
                        <div class="box-header with-border">
                            <h3 class='box-title'>{{ $label }}</h3>
                            <div class="box-tools pull-right">
                                <button class="btn btn-box-tool present" data-widget="collapse" data-toggle="tooltip" title="Collapse/Expand"><i class="fa fa-minus"></i></button>
                            </div>
                        </div>
                        <div class='box-body'>
                            {{ $formResult->exportSectionToHTML($form, $section, $candidate) }}
                        </div>
                        <div class="box-footer"></div><!-- /.box-footer-->
                    </div><!-- /.box -->
                @else
                <div class="box box-{{$box_style}} collapsed-box">
                    <div class="box-header with-border">
                        <h3 class='box-title'>{{ $label }}</h3>
                        <div class="box-tools pull-right">
                            <button class="btn btn-box-tool present" data-widget="collapse" data-toggle="tooltip" title="Collapse/Expand"><i class="fa fa-plus"></i></button>
                        </div>
                    </div>
                    <div class='box-body' style='display: none;'>
                        {{ $formResult->exportSectionToHTML($form, $section, $candidate) }}
                    </div>
                    <div class="box-footer"></div><!-- /.box-footer-->
                </div><!-- /.box -->
                @endif
            @endfor
            <div class="box box-{{$box_style}}">
                <div class="box-header with-border">
                    <button type="submit" class="btn btn-info" id="confirmV">Submit Values to {{$source}}</button>
                    <button role="button" class="btn btn-mini btn-{{$box_style}} pull-right btn-click-action masterbutton"
                        data-widget="collapseAll" data-toggle="tooltip" title="Collapse/Expand All">
                        <i class='fa fa-plus'></i>
                    </button>
                </div>
            </div>
        </form>
        </div><!-- /.col -->

    </div><!-- /.row -->
@endsection

@section('local_scripts')
<!-- bootstrap datepicker -->
<script src={{ asset("/bower_components/AdminLTE/plugins/datepicker/bootstrap-datepicker.js") }}></script>
<!-- Select2 -->
<script src={{ asset("/bower_components/AdminLTE/plugins/select2/select2.full.min.js") }}></script>
<script type="text/javascript">
    $(function () {
      // Replace the <textarea id="editor1"> with a CKEditor
      // instance, using default configuration.
      $(".select2").select2({
          theme: "bootstrap"
      });
    });

    var btnClassClick = function(e) {
        if ($("i", this).hasClass("fa-plus")) {
            $state = "plus";
        } else {
            $state = "minus";
        }
        $(".masterbutton").each(function(e) {
            $("i",this).toggleClass("fa fa-plus fa fa-minus");
        });
        //find all the divs that have data in them, ignore the empty ones
        $(".present").each(function(e) {
            if ($state=="plus") {
                //expand
                if ($("i",this).hasClass("fa-plus")) {
                    this.click();
                }
            } else {
                if ($("i",this).hasClass("fa-minus")) {
                    this.click();
                }
            }
        });
        return false;
    }
    $('.btn-click-action').on('click', btnClassClick);


    //Date picker
    $('.mydatepicker').datepicker({
         todayBtn: "linked",
         language: "en-UK",
         autoclose: true,
         todayHighlight: true,
         format: 'dd/mm/yyyy'
    });



</script>
@endsection

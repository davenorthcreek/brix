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
            <form method="post" enctype="multipart/form-data" id="confirmValues" action='{{ url("register/$source/$index") }}'>
            <!--{{route("confirmValues", ["source" => $source])}}' -->
                <input type='hidden' name='source' value="{{$source}}">
                <input type='hidden' name='subform' value="{{$index}}">
                <input type='hidden' name='email' value="{{$email}}">
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
                <div class="box box-{{$box_style}} ">
                    <div class="box-header with-border">
                        <h3 class='box-title'>{{ $label }}</h3>
                        <div class="box-tools pull-right">
                            <button class="btn btn-box-tool present" data-widget="collapse" data-toggle="tooltip" title="Collapse/Expand"><i class="fa fa-minus"></i></button>
                        </div>
                    </div>
                    <div class='box-body'>
                        {{ $formResult->exportSectionToHTML($form, $section, $candidate, $exceptions) }}
                    </div>
                    <div class="box-footer"></div><!-- /.box-footer-->
                </div><!-- /.box -->
            @endfor
            <div class="box box-{{$box_style}}">
                <div class="box-header with-border">
                    <button type="submit" class="btn btn-info" id="confirmV">{{$next}}</button>
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
<script src={{ asset("/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js") }}></script>
<!-- Select2 -->
<script src={{ asset("/bower_components/select2/dist/js/select2.full.min.js") }}></script>
<!-- Intl-Tel-Input -->
<script src={{ asset("/bower_components/intl-tel-input/build/js/intlTelInput.min.js") }}></script>

<script type="text/javascript">
    $(function () {
      $(".select2").select2({
          theme: "bootstrap"
      });
    });

    $(function () {
      $(".select2_2").select2({
          theme: "bootstrap",
          maximumSelectionLength: 2
      });
    });

    $(function () {
      $(".select2_3").select2({
          theme: "bootstrap",
          maximumSelectionLength: 3
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

    var dt = new Date();
    dt.setFullYear(new Date().getFullYear()-18);
    console.log(dt);
    //DateofBirth picker
    $('.mydateofbirthpicker').datepicker({
        language: "en-UK",
        autoclose: true,
        format: 'dd/mm/yyyy',
        changeMonth: true,
        changeYear: true,
        yearRange: '-110:-18',
        showButtonPanel: true,
        endDate : dt
    });

    $('.mydateofbirthpicker').attr('value', '');
    $('.mydateofbirthpicker').attr("option" , {
        minDate: null,
        maxDate: null
    });


     var telInput = $('.my_phone_number'),
        errorMsg = $("#phone-error-msg"),
        validMsg = $("#phone-valid-msg");

      var input = document.querySelector(".my_phone_number");
      window.intlTelInput(input, {
         utilsScript: "{{asset ("/bower_components/intl-tel-input/build/js/utils.js") }}",
         initialCountry: "au",
         onlyCountries: ["au"],
     });

     var reset_phone = function() {
         telInput.removeClass("error");
         errorMsg.addClass("hide");
         validMsg.addClass("hide");
     }

     // on blur: validate
     telInput.blur(function() {
      reset_phone();
      if ($.trim(telInput.val())) {
        var input = document.querySelector(".my_phone_number");
        if (window.intlTelInput(input, "isValidNumber")) {
          validMsg.removeClass("hide");
        } else {
          telInput.addClass("error");
          errorMsg.removeClass("hide");
        }
      }
    });

    // on keyup / change flag: reset
    telInput.on("keyup change", reset_phone);


     $("input:radio[name='none*Preferred payment method[]']").change(function() {
         var rad = this.value;
         if (rad == 'TFN') {
             $('#brix00rf0024').prop("disabled", false );
             $('#brix00rf0024_label').css('display', "block");
             $('#brix00rf0025').prop("disabled", true );
             $('#brix00rf0025_label').css('display', "none");
         }
         if (rad == 'ABN') {
             $('#brix00rf0024').prop("disabled", true);
             $('#brix00rf0024_label').css('display', "none");
             $('#brix00rf0025').prop("disabled", false);
             $('#brix00rf0025_label').css('display', "block");
         }
     });

     $("#confirmValues").on('submit', function(e) {
        var isvalid = telInput.intlTelInput("isValidNumber");
        if (!isvalid) {
            e.preventDefault();
            alert("Please provide a valid mobile number.");
        } else {
            $("#confirmV").text("Sending...");
            $("#confirmV").prop("disabled", true);
        }
    });

    function getvalues(f)
    {
        var form=$("#"+f);
        var str='';
        $("input:not('input:submit')", form).each(function(i){
            str+='\n'+$(this).prop('name')+': '+$(this).val();
        });
        return str;
    }

</script>
@endsection

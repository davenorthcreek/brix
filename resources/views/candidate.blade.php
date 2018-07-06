@extends('admin_template')

@section('content')
    <div class='row'>
        <div class='col-md-9'>
            @if(isset($errormessage))
                <div class="panel panel-danger">
                    <div class="panel-heading">{{ $errormessage['message'] }}</div>
                    <div class="panel-body">
                        @foreach ($errormessage['errors'] as $error)
                            Property:&nbsp;<strong>{{$error['propertyName'] }}</strong><br>
                            Value:&nbsp;&nbsp;&nbsp;<strong>{{$thecandidate->get_a_string($thecandidate->get($error['propertyName'])) }}</strong><br>
                            Severity:&nbsp;<strong>{{$error['severity'] }}</strong><br>
                            Issue:&nbsp;&nbsp;&nbsp;<strong>{{$error['type'] }}</strong><br><hr>
                        @endforeach
                    </div>
                </div>
            @endif
            <!-- Box -->
            <div class="box box-{{$box_style}}">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ $message }}</h3>
                </div>
                <div class="box-body">
                    <h3>
                        Thank you for registering with {{$fullSource}}.<br>
                        Please ensure you email us a copy of your Passport and a copy of your White Card to
                        <a href="mailto:{{$adminEmail}}">{{$adminEmail}}</a>.<br>
                        You can download a timesheet via our website <a href="{{$homepage}}">{{$homepage}}</a>.
                    </h3>
                    <?php $thecandidate->exportSummaryToHTML($form, $box_style) ?>
                </div><!-- /.box-body -->
                <div class="box-footer">

                </div><!-- /.box-footer-->
            </div><!-- /.box -->
        </div><!-- /.col -->

    </div><!-- /.row -->
@endsection

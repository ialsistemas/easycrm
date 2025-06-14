<link rel="stylesheet" href="{{ asset('auth/css/v2/style.css') }}">
<div class="row">
    <div class="col-md-12">
        <div class="user content-card">
            <ul>
                <li><h5 class="name-client">{{ $Cliente->nombres." ".$Cliente->apellidos }}</h5></li>

                @if($Cliente->estado_detalle_id != \easyCRM\App::$ESTADO_DETALLE_MATRICULADO)
                <li>Interesado en
                    <select name="carrera_id" id="carrera_id" class="form-input">
                        @foreach($Carreras as $q)
                            <option value="{{ $q->id }}" {{ $Cliente->carrera_id == $q->id ? "selected" : ""}}>{{ $q->name }}</option>
                        @endforeach
                    </select>
                </li>
                @endif

                <li>Viene desde <span class="text-lowercase">{{ $Cliente->fuentes != null ? $Cliente->fuentes->name : "-"  }}</span></li>
            </ul>
            <h5> </h5>

        </div>
    </div>
</div>
<form enctype="multipart/form-data" action="{{ in_array($Cliente->estado_detalle_id, [\easyCRM\App::$ESTADO_DETALLE_MATRICULADO , \easyCRM\App::$ESTADO_DETALLE_REINGRESO]) ? route('user.client.storeSeguimientoAdicional') : route('user.client.storeSeguimiento') }}" id="registroSeguimiento" method="POST"
    data-ajax="true" data-close-modal="true" data-ajax-loading="#loading" data-ajax-success="OnSuccessRegistroSeguimiento" data-ajax-failure="OnFailureRegistroSeguimiento">
    @csrf
    <input type="hidden" id="id" name="id" value="{{ $Cliente->id }}">
    <input type="hidden" id="carrera_hidden_id" name="carrera_hidden_id" value="{{ $Cliente->carrera_id }}">
    <div class="row">
    <div class="col-md-4">
        <div class="user-info content-card information">
            <div class="sub-title text-center">
                <p>Sales Pipeline</p>
            </div>
            <div class="row">
                <div class="col-md-3"><div class="progress-line active"></div></div>
                <div class="col-md-3"><div class="progress-line {{ in_array($Cliente->estado_id, [\easyCRM\App::$ESTADO_SEGUIMIENTO, \easyCRM\App::$ESTADO_OPORTUNUDAD, \easyCRM\App::$ESTADO_CIERRE]) ? "active" : "" }}"></div></div>
                <div class="col-md-3"><div class="progress-line {{ in_array($Cliente->estado_id, [\easyCRM\App::$ESTADO_OPORTUNUDAD, \easyCRM\App::$ESTADO_CIERRE]) ? "active" : "" }}"></div></div>
                <div class="col-md-3"><div class="progress-line {{ in_array($Cliente->estado_id, [\easyCRM\App::$ESTADO_CIERRE]) ? "active" : "" }}"></div></div>
            </div>
            <hr>
            <table>
                <tbody>
                    <tr>
                        {{-- <td><label for="dni">DNI: </label></td> --}}
                        <td style="padding-left:0px !important;">
                            <select name="" id="tipo_do" class="documento" style="width:100px;">
                                <option id="tipo_do_val1" value="1">DNI</option>
                                <option id="tipo_do_val2" value="2">CARNÉT DE EXTRANJERIA</option>
                                <option id="tipo_do_val3" value="3">OTROS</option>
                            </select>
                        </td>
                        <div class="alert alert-success alert-dismissible fade show" role="alert" id="alerta-cierre" hidden>
                            <img src="https://cdn-icons-png.flaticon.com/512/5623/5623014.png" style="margin-right:10px; margin-bottom:5px;" width="20px" alt="">
                            <strong>Por favor!</strong> Revise y valide el documento de identidad de este lead.
                        </div>
                        <td>
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <input type="text" class="form-input buscarDNI" name="dni" id="dni" minlength="8" maxlength="15" value="{{ $Cliente->dni }}" autocomplete="off" required>
                                <span data-valmsg-for="dni"></span>
                                @if ($Cliente->estado_detalle_id != 8)
                                    <button class="btn btn-primary" type="button" id="seacrhReniec"><img src="{{ asset('assets/img/log-reniec.png') }}" style="width: 40px;" alt="log-reniec"></button>
                                @endif
                            </div>
                        </td>
                        {{-- <td id="btn_buscar" hidden><a href="javascript:void(0)" class="btn btn-sm btn-primary btn-buscar-dni">Buscar</a></td> --}}
                    </tr>
                    <tr>
                        <td><label for="nombres">Nombres: </label></td>
                        <td><input type="text" class="form-input" id="nombres" name="nombres" value="{{ $Cliente->nombres }}" autocomplete="off" required @if ($Cliente->estado_detalle_id == 8 || strlen($Cliente->dni) == 8) readonly @endif>
                            <span data-valmsg-for="nombres"></span>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="apellidos">Apellidos: </label></td>
                        <td><input type="text" class="form-input" id="apellidos" name="apellidos" value="{{ $Cliente->apellidos }}" autocomplete="off" required @if ($Cliente->estado_detalle_id == 8 || strlen($Cliente->dni) == 8) readonly @endif>
                            <span data-valmsg-for="apellidos"></span>
                        </td>
                        <input type="hidden" name="apellido_paterno" id="apellidoPaterno">
                        <input type="hidden" name="apellido_materno" id="apellidoMaterno">
                    </tr>
                    <tr>
                        <td><label for="fecha_nacimiento">Fecha Nacimiento: </label></td>
                        <td><input type="date" class="form-input" id="fecha_nacimiento" name="fecha_nacimiento" value="{{ $Cliente->fecha_nacimiento != null ? \Carbon\Carbon::parse($Cliente->fecha_nacimiento)->format('Y-m-d') : "-" }}" autocomplete="off" required @if ($Cliente->estado_detalle_id == 8 || strlen($Cliente->dni) == 8) readonly @endif>
                            <span data-valmsg-for="fecha_nacimiento"></span>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="celular">Celular: </label></td>
                        <td><input type="text" class="form-input" name="celular" id="celular" minlength="9" maxlength="15" value="{{ $Cliente->celular }}" autocomplete="off" onkeypress="return isNumberKey(event)" required data-statu="{{ $Cliente->estado_detalle_id }}" @if ($Cliente->estado_detalle_id == 8) readonly @endif>
                            <span data-valmsg-for="celular"></span>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="whatsapp">Whatsapp: </label></td>
                        <td><input type="text" class="form-input" name="whatsapp" id="whatsapp" minlength="9" maxlength="15" value="{{ $Cliente->whatsapp != null ? $Cliente->whatsapp : "" }}" autocomplete="off" onkeypress="return isNumberKey(event)" required @if ($Cliente->estado_detalle_id == 8) readonly @endif>
                            <span data-valmsg-for="whatsapp"></span>
                        </td>
                        <td><a href="javascript:sendMessage({{ $Cliente->whatsapp }})" id="whatsapp_link" title="Enviar un mensjae a {{ $Cliente->whatsapp }}" data-message="{{ $Cliente->whatsapp }}"><img src="/auth/image/icon/whatsApp.png" alt=""></a></td>
                    </tr>
                    <tr>
                        <td><label for="email">Email: </label></td>
                        <td><input type="email" class="form-input" name="email" id="email" value="{{ $Cliente->email }}" autocomplete="off"  required @if ($Cliente->estado_detalle_id == 8) readonly @endif>
                            <span data-valmsg-for="email"></span>
                        </td>
                        <td><a href="mailto:{{ $Cliente->email }}" id="gmail" title="Enviar un correo a {{ $Cliente->email }}" data-mail="{{ $Cliente->email }}"><img src="/auth/image/icon/Mail.png" alt=""></a></td>
                    </tr>
                    <tr>
                        <td><label for="provincia_id">Provincia: </label></td>
                        <td><select name="provincia_id" id="provincia_id" class="form-input" required @if ($Cliente->estado_detalle_id == 8) disabled @endif>
                                @foreach($Provincias as $q)
                                    <option value="{{ $q->id }}" {{ $Cliente->provincia_id == $q->id ? "selected" : ""}}>{{ $q->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        @if ($Cliente->estado_detalle_id == 8)
                            <input type="hidden" name="provincia_id" value="{{ $Cliente->provincia_id }}">
                        @endif
                    </tr>
                    <tr>
                        <td><label for="distrito_id">Distrito: </label></td>
                        <td><select name="distrito_id" id="distrito_id" class="form-input" @if ($Cliente->estado_detalle_id == 8) disabled @endif>
                                @foreach($Distritos as $q)
                                    <option value="{{ $q->id }}" {{ $Cliente->distrito_id == $q->id ? "selected" : ""}}>{{ $q->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        @if ($Cliente->estado_detalle_id == 8)
                            <input type="hidden" name="distrito_id" value="{{ $Cliente->distrito_id }}">
                        @endif
                    </tr>
                    <tr class="direccion_matricula {{ $Cliente->estado_detalle_id == \easyCRM\App::$ESTADO_DETALLE_MATRICULADO ? "" : "hidden" }}">
                        <td><label for="direccion">Dirección: </label></td>
                        <td><input type="text" class="form-input" name="direccion" id="direccion" value="{{ $Cliente->direccion ?? '' }}" autocomplete="off" @if ($Cliente->estado_detalle_id == 8) readonly @endif>
                            <span data-valmsg-for="direccion"></span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-8">

{{--         <div class="alert alert-warning alert-dismissible fade show" role="alert" id="alerta-cierre" hidden>
            <img src="https://cdn-icons-png.flaticon.com/512/5623/5623014.png" style="margin-right:10px; margin-bottom:5px;" width="20px" alt="">
            <strong>Por favor!</strong> Revise y valide el documento de identidad de este lead.
        </div> --}}

        <div class="user-action content-card">

            <div class="content-actions-client">
                @if($Cliente->estado_detalle_id != \easyCRM\App::$ESTADO_DETALLE_MATRICULADO)
                <div id="accionRealizada">
                <h5>Tarea realizada</h5>
                <hr>
                <input type="hidden" id="user_id_register" name="user_id_register" users-id="{{ \Illuminate\Support\Facades\Auth::guard('web')->user()->id }}">
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-4">
                            <select name="accion_id" class="form-input"  id="accion_id" required>
                                <option value="">-- Acción --</option>
                                @foreach($Acciones as $q)
                                    <option value="{{ $q->id }}">{{ $q->name }}</option>
                                @endforeach
                            </select>
                            <span data-valmsg-for="accion_id"></span>
                        </div>
                        <div class="col-md-4">
                            <select name="estado_id" class="form-input"  id="estado_id" required>
                                <option value="">-- Estado --</option>
                                @foreach($Estados as $q)
                                    <option value="{{ $q->id }}">{{ $q->name }}</option>
                                @endforeach
                            </select>
                            <span data-valmsg-for="estado_id"></span>
                        </div>
                        <div class="col-md-4">
                            <select name="estado_detalle_id" class="form-input"  id="estado_detalle_id" required>
                                <option value="">-- Estado Detalle --</option>
                            </select>
                            <span data-valmsg-for="estado_detalle_id"></span>
                        </div>
                    </div>
                    <div id="mainContainer" style="display: none;">
                        <div class="form-group" style="margin-top: 20px">
                            <div class="row">
                                <div class="col-md-12 mt-3">
                                    <div class="row">
                                        <div class="col-lg-6 col-12">
                                            <div class="containerModalidad">
                                                <select name="modalidad_pago" class="form-input" id="modalidad_pago">
                                                    <option value="1" selected>Modalidad de pago: <b>Presencial</b></option>
                                                    <option value="2">Modalidad de pago: <b>Virtual</b></option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-lg-6 col-12">
                                            <div class="containerModalidad">
                                                <select name="codeWaiverSelect" class="form-input" id="codeWaiverSelect">
                                                    <option value="1" selected>Renuncia de codigo: <b>SI</b></option>
                                                    <option value="0">Renuncia de codigo: <b>NO</b></option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-lg-6 col-12">
                                            <div class="containerModalidad mt-10">
                                                <select name="fullPayment" class="form-input" id="fullPayment">
                                                    <option value="1" selected>Pago Completo: <b>SI</b></option>
                                                    <option value="0">Pago Completo: <b>NO</b></option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-lg-6 col-12">
                                            <div class="containerModalidad mt-10">
                                                <select name="mayor" class="form-input" id="mayor">
                                                    <option value="1" selected>Mayor de edad: <b>Si</b></option>
                                                    <option value="0">Mayor de edad: <b>No</b></option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <label for="dniFront" style="margin-top: 10px;">Foto del DNI (Parte Frontal) - Opcional</label>
                                    <input type="file" name="dniFront" id="dniFront" class="form-input" accept="image/png, image/jpeg, image/jpg">
                                    
                                    <label for="dniRear" style="margin-top: 10px;">Foto del DNI (Parte Posterior) - Opcional</label>
                                    <input type="file" name="dniRear" id="dniRear" class="form-input" accept="image/png, image/jpeg, image/jpg">

                                    <label for="codeWaiver" style="margin-top: 10px;">Foto de la Renuncia Código - Opcional</label>
                                    <input type="file" name="codeWaiver" id="codeWaiver" class="form-input" accept="image/png, image/jpeg, image/jpg">
                                    
                                    <label for="izyPay" style="margin-top: 10px;">  Subir foto del comprobante (IZYPAY) – <strong>Obligatorio solo si el pago es con YAPE, PLIN o IZYPAY y la modalidad es Virtual</strong></label>
                                    <input type="file" name="izyPay" id="izyPay" class="form-input" accept="image/png, image/jpeg, image/jpg">

                                    <label for="vaucher" style="margin-top: 10px; color: #721c24;">  Subir foto del comprobante de pago – <strong>Obligatorio si la modalidad es virtual</strong>. En modalidad presencial, <strong>no es obligatorio si el pago es en efectivo</strong>.</label>
                                    <input type="file" name="vaucher" id="vaucher" class="form-input" accept="image/png, image/jpeg, image/jpg">

                                    <div id="containerAdditional" style="display: none;">
                                        <label for="additionalVoucher" style="margin-top: 10px;">Foto del Comprobante de Pago Adicional - Opcional </label>
                                        <input type="file" name="additionalVoucher" id="additionalVoucher" class="form-input" accept="image/png, image/jpeg, image/jpg">
                                    </div>

                                    <label for="schoolName" style="margin-top: 10px;">Nombre del Colegio</label>
                                    <input type="text" name="schoolName" id="schoolName" class="form-input">

                                    <label for="completionDate" style="margin-top: 10px;">Fecha de Termino</label>
                                    <input type="date" name="completionDate" id="completionDate" class="form-input">
                                </div>
                            </div>
                        </div>
                    </div>                    
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-3">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-12">
                            <textarea name="comentario" id="comentario" class="form-input" cols="30" rows="2" placeholder="Comentario" required></textarea>
                            <span data-valmsg-for="comentario"></span>
                        </div>
                    </div>
                </div>
                </div>
                    <div id="proximaAccion" class="form-group">
                        <h5>Nueva tarea</h5>
                        <hr>
                        <div class="row">
                            <div class="col-md-4">
                                <select name="accion_realizar_id" class="form-input"  id="accion_realizar_id">
                                    <option value="">-- Acción a Realizar --</option>
                                    @foreach($Acciones as $q)
                                        <option value="{{ $q->id }}">{{ $q->name }}</option>
                                    @endforeach
                                </select>
                                <span data-valmsg-for="accion_realizar_id"></span>
                            </div>
                            <div class="col-md-4">
                                <input type="date" name="fecha_accion_realizar" class="form-input" id="fecha_accion_realizar">
                                <span data-valmsg-for="fecha_accion_realizar"></span>
                            </div>
                            <div class="col-md-4">
                                <select name="hora_accion_realizar" class="form-input" id="hora_accion_realizar">
                                    <option value="">-- Hora --</option>
                                    <option value="8:00">8:00 HRS</option>
                                    <option value="9:00">9:00 HRS</option>
                                    <option value="10:00">10:00 HRS</option>
                                    <option value="11:00">11:00 HRS</option>
                                    <option value="12:00">12:00 HRS</option>
                                    <option value="13:00">13:00 HRS</option>
                                    <option value="14:00">14:00 HRS</option>
                                    <option value="15:00">15:00 HRS</option>
                                    <option value="16:00">16:00 HRS</option>
                                    <option value="17:00">17:00 HRS</option>
                                    <option value="18:00">18:00 HRS</option>
                                    <option value="19:00">19:00 HRS</option>
                                    <option value="20:00">20:00 HRS</option>
                                    <option value="21:00">21:00 HRS</option>
                                </select>
                                <span data-valmsg-for="hora_accion_realizar"></span>
                            </div>
                        </div>
                    </div>
                @if(\Illuminate\Support\Facades\Auth::guard('web')->user()->profile_id != \easyCRM\App::$PERFIL_RESTRINGIDO)
                    <div id="datosAdicionales" class="form-group hidden">
                        <h5>Datos adicionales</h5>
                        <hr>

                        <div id="datosSemiPresencial" class="row {{$Cliente->carreras != null && $Cliente->carreras->semipresencial ? "" : "hidden" }}">
                            <div class="col-md-4">
                                <select name="presencial_sede_id" class="form-input" id="presencial_sede_id">
                                    <option value="">-- Sede --</option>
                                    @foreach($PresencialSedes as $q)
                                        <option value="{{ $q->id }}">{{ $q->name }}</option>
                                    @endforeach
                                </select>
                                <span data-valmsg-for="presencial_sede_id"></span>
                            </div>
                            <div class="col-md-4">
                                <select name="presencial_turno_id" class="form-input" id="presencial_turno_id">
                                    <option value="">-- Turno --</option>
                                    @foreach($Turnos as $q)
                                        <option value="{{ $q->id }}">{{ $q->name }}</option>
                                    @endforeach
                                </select>
                                <span data-valmsg-for="presencial_turno_id"></span>
                            </div>
                            <div class="col-md-4">
                                <select name="presencial_horario_id" class="form-input" id="presencial_horario_id">
                                    <option value="">-- Horario --</option>
                                </select>
                                <span data-valmsg-for="presencial_horario_id"></span>
                            </div>
                        </div>

                        {{-- ESTADO CIERRE --}}
                        <div class="form-group row mt-15">
                            <div class="col-md-4">
                                <select name="sede_id" class="form-input" id="sede_id">
                                        <option value="">-- Sede --</option>
                                    @foreach($Sedes as $q)
                                        <option value="{{ $q->id }}">{{ $q->name }}</option>
                                    @endforeach
                                </select>
                                <span data-valmsg-for="sede_id"></span>
                            </div>
                            <div class="col-md-4">
                                <select name="local_id" class="form-input" id="local_id">
                                    <option value="">-- Local --</option>
                                </select>
                                <span data-valmsg-for="local_id"></span>
                            </div>
                            <div class="col-md-4">
                                <select name="turno_id" class="form-input" id="turno_id">
                                    <option value="">-- Turno --</option>
                                    @foreach($Turnos as $q)
                                        <option value="{{ $q->id }}">{{ $q->name }}</option>
                                    @endforeach
                                </select>
                                <span data-valmsg-for="turno_id"></span>
                            </div>
                        </div>
                        <div class="form-group row mt-15">
                            <div class="col-md-4">
                                <select name="horario_id" class="form-input" id="horario_id">
                                    <option value="">-- Horario --</option>
                                </select>
                                <span data-valmsg-for="horario_id"></span>
                            </div>
                            <div class="col-md-4">
                                <select name="tipo_operacion_id" class="form-input" id="tipo_operacion_id">
                                    <option value="">-- Tipo Operación --</option>
                                    @foreach($TipoOperaciones as $q)
                                        <option value="{{ $q->id }}">{{ $q->name }}</option>
                                    @endforeach
                                </select>
                                <span data-valmsg-for="tipo_operacion_id"></span>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="nro_operacion" class="form-input" maxlength="15" id="nro_operacion" placeholder="Nro Operación" autocomplete="off">
                                <span data-valmsg-for="nro_operacion"></span>
                            </div>
                        </div>
                        <div class="form-group row mt-15">
                            <div class="col-md-4">
                                <input type="text" name="monto" class="form-input decimal" id="monto" placeholder="Monto" autocomplete="off">
                                <span data-valmsg-for="monto"></span>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="nombre_titular" class="form-input" id="nombre_titular" placeholder="Nombre Titular" autocomplete="off">
                                <span data-valmsg-for="nombre_titular"></span>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="codigo_alumno" class="form-input" id="codigo_alumno" placeholder="Código Alumno" autocomplete="off">
                                <span data-valmsg-for="codigo_alumno"></span>
                            </div>
                        </div>
                        <div class="form-group row mt-15">
                            <div class="col-md-4">
                                <input type="text" name="promocion" class="form-input" id="promocion" placeholder="Promoción" autocomplete="off">
                                <span data-valmsg-for="promocion"></span>
                            </div>
                        </div>
                        <div class="form-group mt-15">
                            <div class="row">
                                <div class="col-md-12">
                                    <input type="text" name="observacion" class="form-input" id="observacion" placeholder="Observación" autocomplete="off">
                                    <span data-valmsg-for="observacion"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div id="datosAdicionales" class="form-group hidden">
                        <h5>Datos adicionales</h5>
                        <hr>
                        <div class="form-group row">
                            <div class="col-md-4">
                                <input type="text" class="form-input" id="codigo_reingreso" name="codigo_reingreso" placeholder="CÓDIGO">
                                <span data-valmsg-for="codigo_reingreso"></span>
                            </div>
                            <div class="col-md-4">
                                <select name="semestre_termino_id" class="form-input" id="semestre_termino_id">
                                    <option value="">SEMESTRE QUE TERMINO</option>
                                    @foreach($SemestreTermino as $q)
                                        <option value="{{ $q->id }}">{{ $q->name }}</option>
                                    @endforeach
                                </select>
                                <span data-valmsg-for="semestre_termino_id"></span>
                            </div>
                            <div class="col-md-4">
                                <select name="ciclo_termino_id" class="form-input" id="ciclo_termino_id">
                                    <option value="">CICLO QUE TERMINO</option>
                                    @foreach($Ciclos as $q)
                                        <option value="{{ $q->id }}">{{ $q->name }}</option>
                                    @endforeach
                                </select>
                                <span data-valmsg-for="ciclo_termino_id"></span>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-3">
                                <select name="semestre_inicio_id" class="form-input" id="semestre_inicio_id">
                                    <option value="">SEMESTRE REINICIO</option>
                                    @foreach($SemestreInicio as $q)
                                        <option value="{{ $q->id }}">{{ $q->name }}</option>
                                    @endforeach
                                </select>
                                <span data-valmsg-for="semestre_inicio_id"></span>
                            </div>
                            <div class="col-md-3">
                                <select name="ciclo_inicio_id" class="form-input" id="ciclo_inicio_id">
                                    <option value="">CICLO QUE INICIO</option>
                                    @foreach($Ciclos as $q)
                                        <option value="{{ $q->id }}">{{ $q->name }}</option>
                                    @endforeach
                                </select>
                                <span data-valmsg-for="ciclo_inicio_id"></span>
                            </div>
                            <div class="col-md-3">
                                <select name="mes" class="form-input" id="mes">
                                    <option value="">MES</option>
                                    @foreach($Meses as $q)
                                        <option value="{{ $q->id }}">{{ $q->name }}</option>
                                    @endforeach
                                </select>
                                <span data-valmsg-for="mes"></span>
                            </div>
                            <div class="col-md-3">
                                <select name="cursos_jalados" class="form-input" id="cursos_jalados">
                                    <option value="">CURSOS JALADOS</option>
                                    <option value="1">SI</option>
                                    <option value="0">NO</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group row mt-15">
                            <div class="col-md-4">
                                <input type="text" name="nombre_titular" class="form-input" id="nombre_titular" placeholder="Nombre Titular" autocomplete="off">
                                <span data-valmsg-for="nombre_titular"></span>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="codigo_alumno" class="form-input" id="codigo_alumno" placeholder="Código Alumno" autocomplete="off">
                                <span data-valmsg-for="codigo_alumno"></span>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="promocion" class="form-input" id="promocion" placeholder="Promoción" autocomplete="off">
                                <span data-valmsg-for="promocion"></span>
                            </div>
                        </div>
                        <div class="form-group mt-15">
                            <div class="row">
                                <div class="col-md-12">
                                    <input type="text" name="observacion" class="form-input" id="observacion" placeholder="Observación" autocomplete="off">
                                    <span data-valmsg-for="observacion"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                <div class="form-group text-right">
                    <button type="submit" class="btn-primary mb-20"><i class="fa fa-pencil-square-o"></i> Registrar Tarea</button>
                </div>
                @endif
            </div>

            <div class="mt-20 pb-5">
                <h5>Historial de acciones</h5>
                <hr>
                <div id="content-history">
                    <p>No existe historial registrada actualmente.</p>
                </div>
                <div class="text-center">
                    @if ($Cliente->estado_detalle_id == 8 && Auth::user()->profile_id == 1 || Auth::user()->profile_id == 2 && $Cliente->estado_detalle_id == 8 && $Cliente->lead_approved == 0)
                        <button type="button" class="btn btn-primary" id="seeFromImng">Editar</button>
                        <div id="formInpuImg" style="display: none;margin-top: 10px;margin-bottom: 10px;">
                            <div class="row">
                                <div class="col-lg-10 col-12">
                                    <div class="form-group">
                                        <label for="dniFrontUpdate" style="margin-top: 10px; font-weight: bold;">Foto del DNI (Parte Frontal)</label>
                                        <input type="file" name="dniFrontUpdate" id="dniFrontUpdate" class="form-control" accept="image/png, image/jpeg, image/jpg">
                                    </div>
                                </div>
                                <div class="col-lg-2 col-12">
                                    <div class="checkbox input-delete-check">
                                        <label>
                                            <input type="checkbox" name="dniFrontDelete" id="dniFrontDelete" value="1"> Eliminar Imagen
                                        </label>
                                    </div>
                                </div>
                                <div class="col-lg-10 col-12">
                                    <div class="form-group">
                                        <label for="dniRearUpdate" style="margin-top: 10px;">Foto del DNI (Parte Posterior)</label>
                                        <input type="file" name="dniRearUpdate" id="dniRearUpdate" class="form-input" accept="image/png, image/jpeg, image/jpg">
                                    </div>
                                </div>
                                <div class="col-lg-2 col-12">
                                    <div class="checkbox input-delete-check">
                                        <label>
                                            <input type="checkbox" name="dniRearDelete" id="dniRearDelete" value="1"> Eliminar Imagen
                                        </label>    
                                    </div>
                                </div>
                                <div class="col-lg-10 col-12">
                                    <div class="form-group">
                                        <label for="codeWaiverUpdate" style="margin-top: 10px;">Foto de la Renuncia Código</label>
                                        <input type="file" name="codeWaiverUpdate" id="codeWaiverUpdate" class="form-input" accept="image/png, image/jpeg, image/jpg">
                                    </div>
                                </div>
                                <div class="col-lg-2 col-12">
                                    <div class="checkbox input-delete-check">
                                        <label>
                                            <input type="checkbox" name="codeWaiverDelete" id="codeWaiverDelete" value="1"> Eliminar Imagen
                                        </label>    
                                    </div>
                                </div>
                                <div class="col-lg-10 col-12">
                                    <div class="form-group">
                                        <label for="izyPayUpdate" style="margin-top: 10px;">Foto del IZYPAY</label>
                                        <input type="file" name="izyPayUpdate" id="izyPayUpdate" class="form-input" accept="image/png, image/jpeg, image/jpg">
                                    </div>
                                </div>
                                <div class="col-lg-2 col-12">
                                    <div class="checkbox input-delete-check">
                                        <label>
                                            <input type="checkbox" name="izyPayDelete" id="izyPayDelete" value="1"> Eliminar Imagen
                                        </label>    
                                    </div>
                                </div>
                                <div class="col-lg-10 col-12">
                                    <div class="form-group">
                                        <label for="vaucherUpdate" style="margin-top: 10px;">Foto del Comprobante de Pago</label>
                                        <input type="file" name="vaucherUpdate" id="vaucherUpdate" class="form-input" accept="image/png, image/jpeg, image/jpg">
                                    </div>
                                </div>
                                <div class="col-lg-2 col-12">
                                    <div class="checkbox input-delete-check">
                                        <label>
                                            <input type="checkbox" name="vaucherDelete" id="vaucherDelete" value="1"> Eliminar Imagen
                                        </label>    
                                    </div>
                                </div>
                                @if ($Cliente->completo == 0)
                                    <div class="col-lg-10 col-12">
                                        <div class="form-group">
                                            <label for="additionalVoucherUpdate" style="margin-top: 10px;">Foto del Comprobante de Pago Adicional</label>
                                            <input type="file" name="additionalVoucherUpdate" id="additionalVoucherUpdate" class="form-input" accept="image/png, image/jpeg, image/jpg">
                                        </div>
                                    </div>
                                    <div class="col-lg-2 col-12">
                                        <div class="checkbox input-delete-check">
                                            <label>
                                                <input type="checkbox" name="additionalVoucherDelete" id="additionalVoucherDelete" value="1"> Eliminar Imagen
                                            </label>    
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <label for="schoolNameUpdate" style="margin-top: 10px;">Nombre del Colegio</label>
                            <input type="text" name="schoolNameUpdate" id="schoolNameUpdate" class="form-input">

                            <label for="completionDateUpdate" style="margin-top: 10px;">Fecha de Termino</label>
                            <input type="date" name="completionDateUpdate" id="completionDateUpdate" class="form-input">

                            <br>
                            <button type="button" id="increaseImgs" class="btn btn-secondary">Guardar</button>
                        </div>
                    @endif
                </div>
                @if(in_array($Cliente->estado_detalle_id,[\easyCRM\App::$ESTADO_DETALLE_MATRICULADO, \easyCRM\App::$ESTADO_DETALLE_REINGRESO]))
                    <h5>Nueva oportunidad</h5>
                    <hr>
                    <div id="content-history-adicional">
                        <p>Aún no tienes nuevas oportunidades.</p>
                    </div>
                    <div class="cursosAdicionales">
                        <div class="form-group text-right">
                            <button type="button" class="btn-primary mb-20"><i class="fa fa-plus"></i> Agregar Curso o Carrera</button>
                        </div>
                        <div id="datosAdicionales" class="form-group hidden">
                            <h5>Datos adicionales</h5>
                            <hr>
                            <div class="form-group row">
                                <div class="col-md-4" style="margin-bottom: 15px;">
                                    <select name="modalidad_pago_adicional" class="form-input" id="modalidad_pago_adicional">
                                        <option value="1" selected>Modalidad de pago: <b>Presencial</b></option>
                                        <option value="2">Modalidad de pago: <b>Virtual</b></option>
                                    </select>
                                </div>
                                <div class="col-md-4" style="margin-bottom: 15px;">
                                    <select name="mayor_adicional" class="form-input" id="mayor_adicional">
                                        <option value="1" selected>Mayor de edad: <b>Si</b></option>
                                        <option value="0">Mayor de edad: <b>No</b></option>
                                    </select>
                                </div>
                                <div class="col-md-12" style="margin-bottom: 15px;">
                                    <label for="dniFrontAdditional" style="margin-top: 10px;">Foto del DNI (Parte Frontal) - Opcional</label>
                                    <input type="file" name="dniFrontAdditional" id="dniFrontAdditional" class="form-input not-required" accept="image/png, image/jpeg, image/jpg">
                                    
                                    <label for="dniRearAdditional" style="margin-top: 10px;">Foto del DNI (Parte Posterior) - Opcional</label>
                                    <input type="file" name="dniRearAdditional" id="dniRearAdditional" class="form-input not-required" accept="image/png, image/jpeg, image/jpg">
                                    
                                    <label for="izyPayAdditional" style="margin-top: 10px;">
                                        Subir foto del comprobante (IZYPAY) – <strong>Obligatorio solo si el pago es con YAPE, PLIN o IZYPAY y la modalidad es Virtual</strong>
                                    </label>
                                    <input type="file" name="izyPayAdditional" id="izyPayAdditional" class="form-input not-required" accept="image/png, image/jpeg, image/jpg">

                                    <label for="vaucherAdditional" style="margin-top: 10px; color: #721c24;">
                                        Subir foto del comprobante de pago – <strong>Obligatorio si la modalidad es virtual</strong>. En modalidad presencial, <strong>no es obligatorio si el pago es en efectivo</strong>.
                                    </label>
                                    <input type="file" name="vaucherAdditional" id="vaucherAdditional" class="form-input not-required" accept="image/png, image/jpeg, image/jpg">

                                    <label for="schoolNameAdditional" style="margin-top: 10px;">Nombre del Colegio</label>
                                    <input type="text" name="schoolNameAdditional" id="schoolNameAdditional" class="form-input not-required">

                                    <label for="completionDateAdditional" style="margin-top: 10px;">Fecha de Termino</label>
                                    <input type="date" name="completionDateAdditional" id="completionDateAdditional" class="form-input not-required">
                                </div>
                                <div class="col-md-4">
                                    <select name="modalidad_adicional_id" class="form-input" id="modalidad_adicional_id">
                                        <option value="">-- Modalidad --</option>
                                        @foreach($Modalidades as $q)
                                            <option value="{{ $q->id }}">{{ $q->name }}</option>
                                        @endforeach
                                    </select>
                                    <span data-valmsg-for="modalidad_adicional_id"></span>
                                </div>
                                <div class="col-md-4">
                                    <select name="carrera_adicional_id" class="form-input" id="carrera_adicional_id">
                                        <option value="">-- Carrera o Curso --</option>
                                    </select>
                                    <span data-valmsg-for="carrera_adicional_id"></span>
                                </div>
                                <div class="col-md-4">
                                    <select name="sede_adicional_id" class="form-input" id="sede_adicional_id">
                                        <option value="">-- Sede --</option>
                                        @foreach($Sedes as $q)
                                            <option value="{{ $q->id }}">{{ $q->name }}</option>
                                        @endforeach
                                    </select>
                                    <span data-valmsg-for="sede_adicional_id"></span>
                                </div>
                            </div>


                            <div id="datosSemiPresencialAdicional" class="row hidden">
                                <div class="col-md-4">
                                    <select name="presencial_adicional_sede_id" class="form-input" id="presencial_adicional_sede_id">
                                        <option value="">-- Sede --</option>
                                    </select>
                                    <span data-valmsg-for="presencial_adicional_sede_id"></span>
                                </div>
                                <div class="col-md-4">
                                    <select name="presencial_adicional_turno_id" class="form-input" id="presencial_adicional_turno_id">
                                        <option value="">-- Turno --</option>
                                    </select>
                                    <span data-valmsg-for="presencial_adicional_turno_id"></span>
                                </div>
                                <div class="col-md-4">
                                    <select name="presencial_adicional_horario_id" class="form-input" id="presencial_adicional_horario_id">
                                        <option value="">-- Horario --</option>
                                    </select>
                                    <span data-valmsg-for="presencial_adicional_horario_id"></span>
                                </div>
                            </div>


                            <div class="form-group row mt-15">
                                <div class="col-md-4">
                                    <select name="local_adicional_id" class="form-input" id="local_adicional_id">
                                        <option value="">-- Local --</option>
                                    </select>
                                    <span data-valmsg-for="local_adicional_id"></span>
                                </div>
                                <div class="col-md-4">
                                    <select name="turno_adicional_id" class="form-input" id="turno_adicional_id">
                                        <option value="">-- Turno --</option>
                                        @foreach($Turnos as $q)
                                            <option value="{{ $q->id }}">{{ $q->name }}</option>
                                        @endforeach
                                    </select>
                                    <span data-valmsg-for="turno_adicional_id"></span>
                                </div>
                                <div class="col-md-4">
                                    <select name="horario_adicional_id" class="form-input" id="horario_adicional_id">
                                        <option value="">-- Horario --</option>
                                    </select>
                                    <span data-valmsg-for="horario_adicional_id"></span>
                                </div>
                            </div>


                            <div class="form-group row mt-15">
                                <div class="col-md-4">
                                    <select name="tipo_operacion_adicional_id" class="form-input" id="tipo_operacion_adicional_id">
                                        <option value="">-- Tipo Operación --</option>
                                        @foreach($TipoOperaciones as $q)
                                            <option value="{{ $q->id }}">{{ $q->name }}</option>
                                        @endforeach
                                    </select>
                                    <span data-valmsg-for="tipo_operacion_adicional_id"></span>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" name="nro_operacion_adicional" class="form-input" maxlength="15" id="nro_operacion_adicional" placeholder="Nro Operación" autocomplete="off">
                                    <span data-valmsg-for="nro_operacion_adicional"></span>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" name="monto_adicional" class="form-input decimal" id="monto_adicional" placeholder="Monto" autocomplete="off">
                                    <span data-valmsg-for="monto_adicional"></span>
                                </div>
                            </div>



                            <div class="form-group row mt-15">
                                <div class="col-md-4">
                                    <input type="text" name="nombre_titular_adicional" class="form-input" id="nombre_titular_adicional" placeholder="Nombre Titular" autocomplete="off">
                                    <span data-valmsg-for="nombre_titular_adicional"></span>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" name="codigo_alumno_adicional" class="form-input" id="codigo_alumno_adicional" placeholder="Código Alumno" autocomplete="off">
                                    <span data-valmsg-for="codigo_alumno_adicional"></span>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" name="promocion_adicional" class="form-input" id="promocion_adicional" placeholder="Promoción" autocomplete="off">
                                    <span data-valmsg-for="promocion_adicional"></span>
                                </div>
                            </div>
                            <div class="form-group mt-15">
                                <div class="row">
                                    <div class="col-md-12">
                                        <input type="text" name="observacion_adicional" class="form-input" id="observacion_adicional" placeholder="Observación" autocomplete="off">
                                        <span data-valmsg-for="observacion_adicional"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group text-right">
                                <button type="submit" class="btn-primary mb-20"><i class="fa fa-pencil-square-o"></i> Registrar Matricula</button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="mt-20 pb-5">
                <h5>Historial del registro</h5>
                <hr>
                <div id="content-history-registro">
                <?php $count = 1; ?>
                @for($i = 0; $i < count($HistorialReasignar); $i++ )
                    <div class="item">
                        <div class="number-image">
                            <div>
                                <span>{{ $count++ }}</span>
                            </div>
                        </div>
                        <div class="info-details">
                            <div>
                                <p class="info-details-title">{{ \easyCRM\App::formatDateStringSpanish($HistorialReasignar[$i]->created_at) }}</p>
                                @if ($HistorialReasignar[$i]['vendedores'] != null && $HistorialReasignar[$i]['users'] != null) 
                                    <p>{{ ($HistorialReasignar[$i]['users']->name. " ". $HistorialReasignar[$i]['users']->last_name). ", reasigno este registro a la ASESORA: ". ($HistorialReasignar[$i]['vendedores']->name. " ". $HistorialReasignar[$i]['vendedores']->last_name) }}</p>
                                @elseif ($HistorialReasignar[$i]['vendedores'] == null && $HistorialReasignar[$i]['users'] == null)
                                    <p>El usuario y la Asesora han sido eliminados por lo cual no se puede ver la reasignación</p>
                                @elseif ($HistorialReasignar[$i]['vendedores'] == null && $HistorialReasignar[$i]['users'] != null)
                                    <p>{{ ($HistorialReasignar[$i]['users']->name. " ". $HistorialReasignar[$i]['users']->last_name). ", reasigno este registro a una Asesora que ha sido Eliminada " }}</p>
                                @elseif ($HistorialReasignar[$i]['vendedores'] != null && $HistorialReasignar[$i]['users'] == null)
                                    <p>{{ "Un Usuario Eliminado reasigno este registro a la ASESORA: ". ($HistorialReasignar[$i]['vendedores']->name. " ". $HistorialReasignar[$i]['vendedores']->last_name) }}</p>
                                @endif
                                {{-- <p>{{ ($HistorialReasignar[$i]['users']->name. " ". $HistorialReasignar[$i]['users']->last_name). ", reasigno este registro a la ASESORA: ". ($HistorialReasignar[$i]['vendedores']->name. " ". $HistorialReasignar[$i]['vendedores']->last_name) }}</p> --}}
                            </div>
                        </div>
                    </div>
                @endfor

                <div class="item">
                    <div class="number-image">
                        <div>
                            <span>{{ $count++ }}</span>
                        </div>
                    </div>
                    <div class="info-details">
                        <div>
                            <p class="info-details-title">{{ \easyCRM\App::formatDateStringSpanish($Cliente->created_at) }}</p>
                            @if ($VendedorRegistrado != null)
                                <p>{{ $VendedorRegistrado->vendedores != "" ? ("Se registró este lead a ". $VendedorRegistrado->vendedores->name. " ".$VendedorRegistrado->vendedores->last_name) : "Se elimino al Usuario al que se le Registro este lead" }}</p>
                            {{-- <p>si hay $vendedor resgitrado</p>
                            @else
                            <p>no hay vendedor registrado</p> --}}
                            @endif
                        </div>
                    </div>
                </div>

                {{-- nuevo creado por marco antonio --}}
                
                <div class="item">
                    <div class="number-image">
                        <div>
                            <span>{{ $count++ }}</span>
                        </div>
                    </div>
                    <div class="info-details">
                        <div>
                            <p class="info-details-title">{{ \easyCRM\App::formatDateStringSpanish($Cliente->created_at) }}</p>
                            <p>{{ $VendedorRegistrado->users != null ? ($VendedorRegistrado->users->name. " ".$VendedorRegistrado->users->last_name.' creo este lead.') : 'Se elimino al Usuario que creo este Lead' }}</p>
                        </div>
                    </div>
                </div>

                </div>
            </div>


        </div>

    </div>
</div>
</form>



<script type="text/javascript" src="auth/plugins/inputmask/dist/min/jquery.inputmask.bundle.min.js"></script>
@if ($Cliente->estado_detalle_id == 8)
    <script>
        var idRoleuserLogin = "{{ Auth::user()->profile_id }}";
    </script>
@endif
<script type="text/javascript" src="auth/js/cliente/_Seguimiento.js"></script>
@if ($Cliente->estado_detalle_id != 8)
    <script src="{{ asset('auth/js/cliente/v2/index.js') }}"></script>
@endif
@if ($Cliente->estado_detalle_id == 8)
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        var uploadUrl = "{{ route('user.client.uploadBoxImages') }}";
        var getUrl = "{{ route('user.client.getDataClient') }}";
        var csrfToken = "{{ csrf_token() }}";
        var uploadAdditional = "{{ route('user.client.uploadAdditionalBoxImages') }}";
    </script>
    <script src="{{ asset('auth/js/cliente/v2/increase.js') }}"></script>
    <script src="{{ asset('auth/js/cliente/v2/increaseAddional.js') }}"></script>
@endif

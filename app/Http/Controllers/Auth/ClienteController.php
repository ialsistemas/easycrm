<?php

namespace easyCRM\Http\Controllers\Auth;

use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use easyCRM\Accion;
use easyCRM\App;
use easyCRM\Asesor;
use easyCRM\Carrera;
use easyCRM\Ciclo;
use easyCRM\Cliente;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use easyCRM\ClienteMatricula;
use easyCRM\ClienteSeguimiento;
use easyCRM\Distrito;
use easyCRM\Enterado;
use easyCRM\Estado;
use easyCRM\Exports\ClientesExport;
use easyCRM\Exports\ResumenDiario;
use easyCRM\Fuente;
use easyCRM\HistorialReasignar;
use easyCRM\Horario;
use easyCRM\Imports\ClientesImport;
use easyCRM\Mes;
use easyCRM\Modalidad;
use easyCRM\Notification;
use easyCRM\PresencialSede;
use easyCRM\Profile;
use easyCRM\Provincia;
use easyCRM\Sede;
use easyCRM\Local;
use easyCRM\Semestre;
use easyCRM\TipoOperacion;
use easyCRM\Turno;
use easyCRM\User;
use Illuminate\Http\Request;
use easyCRM\Http\Controllers\Controller;
use easyCRM\Jobs\ExportLeadJob;
use easyCRM\Traits\Consultas;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ClienteController extends Controller
{
    use Consultas;

    public function partialView()
    {
        $Provincias = Provincia::orderBy('name', 'asc')->get();
        $Modalidades = Modalidad::orderBy('name', 'asc')->get();
        /* $Fuentes = Fuente::whereNotIn('id', [App::$FUENTES_GOOGLE_ADS, App::$FUENTES_FACEBOOK_ADS])->orderBy('name', 'asc')->get(); */
        $Fuentes = DB::table('fuentes')->orderBy('name', 'asc')->get();

        $Enterados = Enterado::orderBy('name', 'asc')->get();
        $Carreras = Carrera::all();
        $Ciclos = Ciclo::all();
        $Asesores = Asesor::where('profile_id', App::$PERFIL_VENDEDOR)
            ->where('activo', '1')
            ->where('recibe_lead', '1')
            ->orderBy('name', 'asc')
            ->get();


        return view('auth.cliente._Mantenimiento', [
            'Provincias' => $Provincias, 'Modalidades' => $Modalidades,
            'Fuentes' => $Fuentes, 'Enterados' => $Enterados, 'Ciclos' => $Ciclos, 'Asesores' => $Asesores
        ]);
    }

    public function createView()
    {
        $Provincias = Provincia::orderBy('name', 'asc')->get();
        $Modalidades = Modalidad::orderBy('name', 'asc')->get();
        $Fuentes = Fuente::whereNotIn('id', [App::$FUENTES_GOOGLE_ADS, App::$FUENTES_FACEBOOK_ADS])->orderBy('name', 'asc')->get();
        $Enterados = Enterado::orderBy('name', 'asc')->get();


        return view('auth.cliente.Mantenimiento', [
            'Provincias' => $Provincias, 'Modalidades' => $Modalidades,
            'Fuentes' => $Fuentes, 'Enterados' => $Enterados
        ]);
    }

    public function storeApi(Request $request)
    {
        /* dd($request); */
        $client = new Client();
        /* $client->request('POST', 'https://easycrm.ial.edu.pe/api/cliente/create', */
        $client->request(
            'POST',
            'http://127.0.0.1:8000/api/cliente/create',
            [
                RequestOptions::HEADERS => [
                    'Accept' => "application/json",
                    'Authorization' => "Bearer ZupWuQUrw2vYcH8fzCczPHc5QlTxsK7dB9IhPW42fPRC99i0yIV3iBBtDNGz9T5ECMzN2vCnWSzVKHXTo0Ee3qquxVj52MpbhRLO",
                    'Cache-Control' => "no-cache",
                ],
                RequestOptions::JSON => [
                    "nombres" => $request->nombres,
                    "apellidos" => $request->apellidos,
                    "dni" => $request->dni,
                    "celular" => $request->celular,
                    "email" => $request->email,
                    "fecha_nacimiento" => $request->fecha_nacimiento,
                    "provincia" => 0,
                    "provincia_id" => $request->provincia_id,
                    "distrito_id" => $request->distrito_id,
                    "modalidad_id" => $request->modalidad_id,
                    "carrera_id" => $request->carrera_id,
                    "fuente_id" => $request->fuente_id,
                    "enterado_id" => $request->enterado_id
                ]
            ]
        );
        return response()->json($request->all());
    }

    public function store(Request $request)
    {
        $status = false;
        $user = null;
        $register = false;
        $update = false;
        $duplicado = false;
        $message = null;
        $validator = null;
        $userTurnId = null;
        $reintento = false;

        try {

            DB::beginTransaction();

            $assessor = $this->getAssessorWithMinimumAssignedLeads();

            if (in_array(Auth::guard('web')->user()->profile_id, [App::$PERFIL_VENDEDOR, App::$PERFIL_RESTRINGIDO, App::$PERFIL_PROVINCIA])) {
                $userTurnId = Auth::guard('web')->user()->id;
            } else {
                if (isset($request->name_id)) {
                    // Asigna el ID del usuario especificado en la solicitud
                    $userTurnId = DB::table('users')->select('id')->where('id', $request->name_id)->first()->id;
                } else {
                    // Selecciona al asesor con la menor cantidad de leads
                    $userTurnId = $assessor != null ? $assessor->id : null;
                }
            }

            $clienteExist = Cliente::where('dni', $request->dni)
                ->orWhere('email', $request->email)
                ->orWhere('celular', $request->celular)
                ->orderby('created_at', 'desc')->first();

            if ($clienteExist != null) {
                $hoy = Carbon::now();
                $diaHoy = $hoy->format('d');
                if ($diaHoy >= 16) {
                    $fecha_inicio_act_camp = $hoy->copy()->day(16);
                    $fecha_final_act_camp = $hoy->copy()->addMonthNoOverflow()->day(15);
                } else {
                    $fecha_inicio_act_camp = $hoy->copy()->subMonthNoOverflow()->day(16);
                    $fecha_final_act_camp = $hoy->copy()->day(15);
                }

                if (($clienteExist->created_at >= $fecha_inicio_act_camp  && $clienteExist->created_at <= $fecha_final_act_camp)) {
                    if (($request->modalidad_id == App::$MODALIDAD_CURSO) ||
                        ($request->modalidad_id == App::$MODALIDAD_CARRERA && $clienteExist->modalidad_id == App::$MODALIDAD_CURSO)
                    ) {
                        $Cliente_Cursos = Cliente::where('dni', $request->dni)->orWhere('email', $request->email)->orWhere('celular', $request->celular)->whereNull('deleted_at')
                            ->pluck('carrera_id')->toArray();

                        $Cliente_Carreras = Cliente::where('dni', $request->dni)->orWhere('email', $request->email)->orWhere('celular', $request->celular)->whereNull('deleted_at')
                            ->pluck('modalidad_id')->toArray();

                        if (
                            ($request->modalidad_id == App::$MODALIDAD_CURSO && !in_array($request->carrera_id, $Cliente_Cursos)) ||
                            ($request->modalidad_id == App::$MODALIDAD_CARRERA && !in_array(App::$MODALIDAD_CARRERA, $Cliente_Carreras))
                        ) {
                            $register = true;
                        } else {
                            $duplicado = true;
                        }
                    } else {
                        $duplicado = true;
                    }
                } else {

                    $Cliente_Cursos = Cliente::where('dni', $request->dni)
                        ->orWhere('email', $request->email)
                        ->orWhere('celular', $request->celular)
                        ->pluck('carrera_id')->toArray();

                    if (!in_array($request->carrera_id, $Cliente_Cursos)) {
                        $register = true;
                    } else if (!in_array($clienteExist->estado_id, [App::$ESTADO_CIERRE])) {
                        $reintento = true;
                        $update = true;
                    } else {
                        $duplicado = true;
                    }
                }
            } else {
                $register = true;
            }

            $apellidos = trim($request->apellidos);
            if ($apellidos !== null && $apellidos !== "") {
                if (empty($request->apellido_paterno) || empty($request->apellido_materno)) {
                    $partes = explode(' ', $apellidos);
                    $apellidoPaterno = $partes[0];
                    $apellidoMaterno = count($partes) > 1 ? implode(' ', array_slice($partes, 1)) : '';
                } else {
                    $apellidoPaterno = $request->apellido_paterno;
                    $apellidoMaterno = $request->apellido_materno;
                }
            } else {
                $apellidoPaterno = $request->apellido_paterno ?? '';
                $apellidoMaterno = $request->apellido_materno ?? '';
                $apellidos = trim($apellidoPaterno . " " . $apellidoMaterno);
            }
            $request->merge([
                'user_id' =>  $userTurnId,
                'apellido_paterno' =>  $apellidoPaterno,
                'apellido_materno' =>  $apellidoMaterno,
                'apellidos' =>  $apellidos,
                'estado_id' => App::$ESTADO_NUEVO,
                'estado_detalle_id' => App::$ESTADO_DETALLE_NUEVO,
                'proviene_id' => App::$LLAMADA,
                'ultimo_contacto' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'provincia' => in_array($request->provincia_id, [1, 2]) ? false : true,
                'reasignado' => false,
                'fuente_id' => $reintento ? App::$FUENTE_REINTENTO : $request->fuente_id,
                'deleted_modified_by' => 66,
                'origen_lead' => 'registro_manual'
            ]);

            if (Auth::guard('web')->user()->profile_id == App::$PERFIL_CALL) {
                $validator = Validator::make($request->all(), [
                    'user_id' => 'required',
                    'nombres' => 'required',
                    'apellidos' => 'required',
                    'dni' => ['required', 'min:8', 'max:10'],
                    'celular' => ['required', 'min:9', 'max:15'],
                    'email' => ['required', 'email'],
                    'provincia' => 'required',
                    'provincia_id' => 'required',
                    'distrito_id' => 'required',
                    'modalidad_id' => 'required',
                    'carrera_id' => 'required',
                    'fuente_id' => 'required',
                    'enterado_id' => 'required',
                    'estado_id' => 'required',
                    'estado_detalle_id' => 'required',
                    'deleted_modified_by' => 'required'
                ]);
            } else if (Auth::guard('web')->user()->profile_id == App::$PERFIL_RESTRINGIDO) {
                $validator = Validator::make($request->all(), [
                    'user_id' => 'required',
                    'nombres' => 'required',
                    'apellidos' => 'required',
                    'dni' => ['required', 'min:8', 'max:10'],
                    'celular' => ['required', 'min:9', 'max:15'],
                    'email' => ['required', 'email'],
                    'provincia' => 'required',
                    'provincia_id' => 'required',
                    'distrito_id' => 'required',
                    'modalidad_id' => 'required',
                    'carrera_id' => 'required',
                    'fuente_id' => 'required',
                    'ciclo_id' => 'required',
                    'estado_id' => 'required',
                    'estado_detalle_id' => 'required',
                    'deleted_modified_by' => 'required'
                ]);
            } else {
                $validator = Validator::make($request->all(), [
                    'user_id' => 'required',
                    'nombres' => 'required',
                    'apellidos' => 'required',
                    'dni' => ['required', 'min:8', 'max:10'],
                    'celular' => ['required', 'min:9', 'max:15'],
                    'email' => ['required', 'email'],
                    'provincia' => 'required',
                    'provincia_id' => 'required',
                    'distrito_id' => 'required',
                    'modalidad_id' => 'required',
                    'carrera_id' => 'required',
                    'fuente_id' => 'required',
                    'enterado_id' => 'required',
                    'estado_id' => 'required',
                    'estado_detalle_id' => 'required',
                    'deleted_modified_by' => 'required'
                ]);
            }
            if (!$validator->fails()) {
                if ($duplicado) {
                    return response()->json(['Success' => $status, 'Message' => "El cliente ya se encuentra registrado en esta campaña"]);
                }

                if ($clienteExist != null && $update) {
                    $Cliente = Cliente::find($clienteExist->id);
                    $Cliente->estado_id = App::$ESTADO_REINTENTO;
                    $Cliente->estado_detalle_id = App::$ESTADO_DETALLE_REINTENTO;
                    $Cliente->fuente_id = App::$FUENTE_REINTENTO;
                    if ($Cliente->save()) {
                        $status = true;
                    }
                } else if ($register) {
                    $Cliente = Cliente::create($request->all());

                    if ($Cliente) {
                        $assessor = User::where('id', $userTurnId)->first();
                        if ($assessor) {
                            $assessor->assigned_leads += 1;
                            $assessor->save();
                        }
                    }

                    if (in_array(Auth::guard('web')->user()->profile_id, [App::$PERFIL_VENDEDOR, App::$PERFIL_RESTRINGIDO, App::$PERFIL_PROVINCIA]))
                        $status = true;
                    else {
                        $usersAllIds = DB::table('users')->whereNull('deleted_at')->where('profile_id', App::$PERFIL_VENDEDOR)
                            ->where('activo', 1)->where('recibe_lead', 1)->pluck('id')->toArray();
                        $minId = array_search(min($usersAllIds), $usersAllIds);
                        $maxId = array_search(max($usersAllIds), $usersAllIds);

                        $userTurnId = array_search($userTurnId, $usersAllIds) + 1;

                        if ($userTurnId > $maxId)
                            $userTurnId = $usersAllIds[$minId];
                        else
                            $userTurnId = $usersAllIds[$userTurnId];

                        $user = User::find($userTurnId);
                        $user->turno = true;
                        if ($user->save()) {
                            $status = true;
                            User::where('id', '!=', $user->id)->where('profile_id', App::$PERFIL_VENDEDOR)
                                ->update(['turno' => false]);
                        }
                    }

                    // aca es donde se cambio el registro predeterminado al user_id = 1

                    if ($status) {
                        $HistorialReasignar = new HistorialReasignar();
                        $HistorialReasignar->cliente_id =  $Cliente->id;
                        $HistorialReasignar->user_id =  $request->creador_id;
                        // $HistorialReasignar->user_id =  1;
                        $HistorialReasignar->vendedor_id =  $Cliente->user_id;
                        $HistorialReasignar->observacion = "Registro";


                        $Cliente = Cliente::find($Cliente->id);
                        $Cliente->created_modified_by = auth()->user()->id;

                        if ($HistorialReasignar->save() and $Cliente->save()) $status = true;
                    }
                }
            }

            if ($status) {
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();
            DB::rollBack();
        }

        return response()->json(['Success' => $status, 'Errors' => $validator != null ? $validator->errors() : null, 'Message' => $message]);
    }

    public function partialViewMatriculado($id)
    {
        $Cliente = Cliente::find($id);

        if (
            in_array(Auth::guard('web')->user()->profile_id, [App::$PERFIL_VENDEDOR, App::$PERFIL_RESTRINGIDO]) &&
            $Cliente->user_id != Auth::guard('web')->user()->id
        ) return null;

        $Modalidades = Modalidad::all();
        $Carreras = Carrera::where('modalidad_id', $Cliente->modalidad_id)->get();
        $Turnos = Turno::whereNotIn('id', [App::$TURNO_GLOABAL])->get();
        $Horarios = Horario::where('carrera_id', $Cliente->carrera_id)->where('turno_id', $Cliente->turno_id)->get();

        return view('auth.cliente._Matriculado', [
            'Cliente' => $Cliente, 'Turnos' => $Turnos, 'Horarios' => $Horarios,
            'Modalidades' => $Modalidades, 'Carreras' => $Carreras
        ]);
    }

    public function updateMatriculado(Request $request)
    {
        $Status = false;

        $Cliente = Cliente::find($request->id);

        if (!(in_array(Auth::guard('web')->user()->profile_id, [App::$PERFIL_VENDEDOR, App::$PERFIL_RESTRINGIDO]) &&
            $Cliente->user_id != Auth::guard('web')->user()->id)) {

            $validator = Validator::make($request->all(), [
                'modalidad_id' => 'required',
                'carrera_id' => 'required',
                'turno_id' => 'required',
                'horario_id' => 'required'
            ]);

            if (!$validator->fails()) {
                $Cliente->modalidad_id = $request->modalidad_id;
                $Cliente->carrera_id = $request->carrera_id;
                $Cliente->turno_id = $request->turno_id;
                $Cliente->horario_id = $request->horario_id;
                if ($Cliente->save()) $Status = true;
            }
        }

        return response()->json(['Success' => $Status, 'Errors' => $validator->errors()]);
    }

    public function partialViewSeguimiento($id)
    {
        $Cliente = Cliente::find($id);

        if (
            in_array(Auth::guard('web')->user()->profile_id, [App::$PERFIL_VENDEDOR, App::$PERFIL_RESTRINGIDO, App::$PERFIL_PROVINCIA]) &&
            $Cliente->user_id != Auth::guard('web')->user()->id
        ) return null;

        if (Auth::guard('web')->user()->profile_id == App::$PERFIL_RESTRINGIDO)
            $Estados = Estado::whereNotIn('id', [App::$ESTADO_NUEVO, App::$ESTADO_CIERRE, App::$ESTADO_OTROS])->get();
        else
            $Estados =  Estado::whereNotIn('id', [App::$ESTADO_NUEVO, App::$ESTADO_REINGRESO])->get();

        $Acciones = Accion::orderBy('name', 'asc')->get();
        $Turnos = Turno::whereNotIn('id', [App::$TURNO_GLOABAL])->get();
        $Sedes = Sede::all();
        $Locales = Local::all();
        $PresencialSedes = PresencialSede::all();
        $Modalidades = Modalidad::all();
        $Carreras = Carrera::all();
        $TipoOperaciones = TipoOperacion::all();
        $Provincias = Provincia::all();
        $Distritos = Distrito::where('provincia_id', $Cliente->provincia_id)->get();
        $Meses = Mes::all();
        $SemestreTermino = Semestre::whereIn('division', [App::$DIVISION_SEMESTRE_TERMINO, App::$DIVISION_SEMESTRE_COMPARTIDO])->get();
        $SemestreInicio = Semestre::whereIn('division', [App::$DIVISION_SEMESTRE_INICIO, App::$DIVISION_SEMESTRE_COMPARTIDO])->get();
        $Ciclos = Ciclo::all();
        $HistorialReasignar = HistorialReasignar::where('cliente_id', $id)->where('observacion', '!=', 'Registro')->orderby('created_at', 'desc')->get();
        $VendedorRegistrado = HistorialReasignar::where('cliente_id', $id)->where('observacion', 'Registro')->orderby('created_at', 'desc')->first();

        return view('auth.cliente._Seguimiento', [
            'Cliente' => $Cliente, 'Acciones' => $Acciones, 'Estados' => $Estados, 'Modalidades' => $Modalidades,
            'Turnos' => $Turnos, 'Sedes' => $Sedes, 'Locales' => $Locales, 'PresencialSedes' => $PresencialSedes, 'Carreras' => $Carreras, 'TipoOperaciones' => $TipoOperaciones, 'Provincias' => $Provincias,
            'Distritos' => $Distritos, 'Ciclos' => $Ciclos, 'Meses' => $Meses, 'SemestreTermino' => $SemestreTermino, 'SemestreInicio' => $SemestreInicio,
            'HistorialReasignar' => $HistorialReasignar, 'VendedorRegistrado' => $VendedorRegistrado
        ]);
    }

    public function search_course($id)
    {
        $Turnos = Turno::whereNotIn('id', [App::$TURNO_GLOABAL])->get();
        $PresencialSedes = PresencialSede::all();
        return response()->json(['data' => Carrera::find($id), 'turnos' => $Turnos, 'PresencialSedes' => $PresencialSedes]);
    }

    public function updateDatosContacto(Request $request)
    {
        $Exist = false;
        $Status = false;
        $Title = "Error";
        $Message = "Algo salio mal, verifique los campos ingresados.";

        $Cliente = Cliente::find($request->id);

        if (in_array($Cliente->estado_detalle_id, [App::$ESTADO_DETALLE_MATRICULADO, App::$ESTADO_DETALLE_TRASLADO])) {
            $validator = Validator::make($request->all(), [
                'nombres' => 'required',
                'provincia_id' => 'required',
                'distrito_id' => 'required',
                'dni' => 'required|min:8|max:10',
                'celular' => 'required|min:9|max:15',
                'email' => 'required|email',
                'whatsapp' => 'nullable|min:9|max:15',
                'direccion' => 'required'
            ]);
        } else {
            $validator = Validator::make($request->all(), [
                'nombres' => 'required',
                'carrera_id' => 'required',
                'provincia_id' => 'required',
                'distrito_id' => 'required',
                'dni' => 'required|min:8|max:10',
                'celular' => 'required|min:9|max:15',
                'email' => 'required|email',
                'whatsapp' => 'nullable|min:9|max:15'
            ]);
        }

        $errors = [];
        $ErrorsModel = [];
        if (!$validator->fails()) {

            $validatorDNI = DB::table('clientes')->select('id')->where('modalidad_id', $Cliente->modalidad_id)->where('dni', $request->dni)->whereNull('deleted_at')->first();

            if ($validatorDNI && $validatorDNI->id != $Cliente->id) {
                array_push($errors, ['dni' => ['El dni ya está en uso.']]);
            }

            $validatorCelular = DB::table('clientes')->select('id')->where('modalidad_id', $Cliente->modalidad_id)->where('celular', $request->celular)->first();

            /* if ($validatorCelular && $validatorCelular->id != $Cliente->id) {
                array_push($errors, ['celular' => ['El celular ya está en uso.']]);
            } */

            $validatorEmail = DB::table('clientes')->select('id')->where('modalidad_id', $Cliente->modalidad_id)->where('email', $request->email)->first();

            /* if ($validatorEmail && $validatorEmail->id != $Cliente->id) {
                array_push($errors, ['email' => ['El email ya está en uso.']]);
            } */

            $validatorWhatsapp = DB::table('clientes')->select('id')->where('modalidad_id', $Cliente->modalidad_id)->where('whatsapp', $request->whatsapp)->first();

            /* if ($request->whatsapp && $validatorWhatsapp && $validatorWhatsapp->id != $Cliente->id) {
                array_push($errors, ['whatsapp' => ['El whatsapp ya está en uso.']]);
            } */
        }

        if (count($errors) > 0) {
            $Exist = true;
            $errors = $errors[0];
        } else {
            $errors = $validator->errors();
        }

        if (!$Exist && !$validator->fails()) {
            if ($Cliente->estado_id != 4) {
                $Cliente->nombres = $request->nombres;
                $Cliente->apellidos = $request->apellidos;
                $Cliente->apellido_paterno = $request->apellido_paterno;
                $Cliente->apellido_materno = $request->apellido_materno;

                $Cliente->dni = $request->dni;
                $Cliente->celular = $request->celular;
                $Cliente->whatsapp = $request->whatsapp;
                $Cliente->email = $request->email;
                $Cliente->fecha_nacimiento = $request->fecha_nacimiento;

                $Cliente->apellido_paterno = $request->apellido_paterno ?: null;
                $Cliente->apellido_materno = $request->apellido_materno ?: null;
            }


            if ($Cliente->estado_detalle_id != App::$ESTADO_DETALLE_MATRICULADO) {
                $Cliente->carrera_id = $request->carrera_id;
                $Cliente->modalidad_id = Carrera::find($request->carrera_id)->modalidad_id;
            }

            if (in_array($Cliente->estado_detalle_id, [App::$ESTADO_DETALLE_MATRICULADO, App::$ESTADO_DETALLE_TRASLADO])) {
                $Cliente->direccion = $request->direccion;
            }

            $Cliente->provincia_id = $request->provincia_id;
            $Cliente->distrito_id = $request->distrito_id;
            $Cliente->updated_at = Carbon::now();
            $Cliente->updated_modified_by = auth()->user()->id;

            if ($Cliente->save()) {
                $Status = true;
                $Title = "Datos Actualizados";
                $Message = "Se han guardado los cambios realizados.";
            }
        }

        return response()->json(['Success' => $Status, 'Title' => $Title, 'Message' => $Message, 'Errors' => $errors]);
    }

    public function updateDatosCliente(Request $request)
    {

        $Exist = false;
        $Status = false;
        $Title = "Error";
        $Message = "Algo salio mal, verifique los campos ingresados.";

        $Cliente = Cliente::find($request->id);

        if (in_array($Cliente->estado_detalle_id, [App::$ESTADO_DETALLE_MATRICULADO, App::$ESTADO_DETALLE_TRASLADO])) {
            $validator = Validator::make($request->all(), [
                'nombres' => 'required',
                'provincia_id' => 'required',
                'distrito_id' => 'required',
                'dni' => 'required|min:8|max:10',
                'celular' => 'required|min:9|max:15',
                'email' => 'required|email',
                'whatsapp' => 'nullable|min:9|max:15',
            ]);
        } else {
            $validator = Validator::make($request->all(), [
                'nombres' => 'required',
                'provincia_id' => 'required',
                'distrito_id' => 'required',
                'dni' => 'required|min:8|max:10',
                'celular' => 'required|min:9|max:15',
                'email' => 'required|email',
                'whatsapp' => 'nullable|min:9|max:15'
            ]);
        }

        $errors = [];
        $ErrorsModel = [];
        if (!$validator->fails()) {

            $validatorDNI = DB::table('clientes')->select('id')->where('modalidad_id', $Cliente->modalidad_id)->where('dni', $request->dni)->whereNull('deleted_at')->first();

            if ($validatorDNI && $validatorDNI->id != $Cliente->id) {
                array_push($errors, ['dni' => ['El dni ya está en uso.']]);
            }

            $validatorCelular = DB::table('clientes')->select('id')->where('modalidad_id', $Cliente->modalidad_id)->where('celular', $request->celular)->first();

            /* if ($validatorCelular && $validatorCelular->id != $Cliente->id) {
                array_push($errors, ['celular' => ['El celular ya está en uso.']]);
            } */

            $validatorEmail = DB::table('clientes')->select('id')->where('modalidad_id', $Cliente->modalidad_id)->where('email', $request->email)->first();

            /* if ($validatorEmail && $validatorEmail->id != $Cliente->id) {
                array_push($errors, ['email' => ['El email ya está en uso.']]);
            } */

            $validatorWhatsapp = DB::table('clientes')->select('id')->where('modalidad_id', $Cliente->modalidad_id)->where('whatsapp', $request->whatsapp)->first();

            /* if ($request->whatsapp && $validatorWhatsapp && $validatorWhatsapp->id != $Cliente->id) {
                array_push($errors, ['whatsapp' => ['El whatsapp ya está en uso.']]);
            } */
        }

        if (count($errors) > 0) {
            $Exist = true;
            $errors = $errors[0];
        } else {
            $errors = $validator->errors();
        }

        if (!$Exist && !$validator->fails()) {
            if ($Cliente->estado_id != 4) {
                $Cliente->nombres = $request->nombres;
                $Cliente->apellidos = $request->apellidos;
                $Cliente->apellido_paterno = $request->apellido_paterno;
                $Cliente->apellido_materno = $request->apellido_materno;
                $Cliente->dni = $request->dni;
                $Cliente->celular = $request->celular;
                $Cliente->whatsapp = $request->whatsapp;
                $Cliente->email = $request->email;
                $Cliente->fecha_nacimiento = $request->fecha_nacimiento;

                $Cliente->provincia_id = $request->provincia_id;
                $Cliente->distrito_id = $request->distrito_id;
                $Cliente->direccion = $request->direccion;
                $Cliente->updated_at = Carbon::now();
                $Cliente->updated_modified_by = auth()->user()->id;
            }

            if ($Cliente->save()) {
                $Status = true;
                $Title = "Datos Actualizados";
                $Message = "Se han guardado los cambios realizados.";
            }
        }

        return response()->json(['Success' => $Status, 'Title' => $Title, 'Message' => $Message, 'Errors' => $errors]);
    }

    public function storeSeguimiento(Request $request)
    {
        $status = false;

        try {

            DB::beginTransaction();

            $request->merge([
                'cliente_id' => $request->id
            ]);

            $validator = Validator::make($request->all(), [
                'cliente_id' => 'required',
                'accion_id' => 'required',
                'estado_id' => 'required',
                'estado_detalle_id' => 'required',
                'comentario' => 'required',
                'user_id_register' => 'required'
            ]);

            $CarreraPresencial = Carrera::find($request->carrera_hidden_id);

            if (in_array($request->estado_detalle_id, [App::$ESTADO_DETALLE_MATRICULADO, App::$ESTADO_DETALLE_TRASLADO])) {

                if ($CarreraPresencial != null && $CarreraPresencial->semipresencial) {
                    $validator = Validator::make($request->all(), [
                        'cliente_id' => 'required',
                        'accion_id' => 'required',
                        'estado_id' => 'required',
                        'comentario' => 'required',
                        'estado_detalle_id' => 'required',
                        'turno_id' => 'required',
                        'horario_id' => 'required',
                        'sede_id' => 'required',
                        'local_id' => 'required',
                        'presencial_turno_id' => 'required',
                        'presencial_horario_id' => 'required',
                        'presencial_sede_id' => 'required',
                        'tipo_operacion_id' => 'required',
                        'nro_operacion' => 'required|max:15',
                        'monto' => 'required|numeric',
                        'nombre_titular' => 'required',
                        'codigo_alumno' => 'required',
                        'promocion' => 'required',
                        'observacion' => 'required',
                        'direccion' => 'required'
                    ]);
                } else {
                    $validator = Validator::make($request->all(), [
                        'cliente_id' => 'required',
                        'accion_id' => 'required',
                        'estado_id' => 'required',
                        'comentario' => 'required',
                        'estado_detalle_id' => 'required',
                        'turno_id' => 'required',
                        'horario_id' => 'required',
                        'sede_id' => 'required',
                        'local_id' => 'required',
                        'tipo_operacion_id' => 'required',
                        'nro_operacion' => 'required|max:15',
                        'monto' => 'required|numeric',
                        'nombre_titular' => 'required',
                        'codigo_alumno' => 'required',
                        'promocion' => 'required',
                        'observacion' => 'required',
                        'direccion' => 'required'
                    ]);
                }
            } else if ($request->estado_detalle_id == App::$ESTADO_DETALLE_REINGRESO) {
                $validator = Validator::make($request->all(), [
                    'codigo_reingreso' => 'required',
                    'semestre_termino_id' => 'required',
                    'ciclo_termino_id' => 'required',
                    'semestre_inicio_id' => 'required',
                    'ciclo_inicio_id' => 'required',
                    'mes' => 'required',
                    'cursos_jalados' => 'required'
                ]);
            }

            try {
                if ($request->estado_detalle_id == 8) {
                    DB::table('clientes')
                        ->where('id', $request->cliente_id)
                        ->update([
                            'code_waiver' => $request->codeWaiverSelect,
                            'mayor' => $request->mayor,
                            'completo' => $request->fullPayment,
                            'updated_at' => Carbon::now(),
                        ]);
                    if ($request->modalidad_pago !== null && $request->modalidad_pago !== "") {
                        DB::table('clientes')
                            ->where('id', $request->cliente_id)
                            ->update([
                                'modalidad_pago' => $request->modalidad_pago,
                                'updated_at' => Carbon::now(),
                            ]);
                    } else {
                        dd('Error: Debes seleccionar alguna modalidad de pago.');
                    }
                    $imgData = DB::table('client_registration_images')
                        ->where('id_client', $request->cliente_id)
                        ->first();

                    if ($request->tipo_operacion_id != 3) {
                        if (!$imgData && !$request->hasFile('vaucher')) {
                            dd('Error: Debe subir la imagen del comprobante de pagos.');
                        }
                    }

                    if ($request->modalidad_pago == 2) {
                        if ($request->tipo_operacion_id == 4 || $request->tipo_operacion_id == 5 || $request->tipo_operacion_id == 6) {
                            if (!$imgData) {
                                if (!$request->hasFile('vaucher') || !$request->hasFile('izyPay')) {
                                    dd('Error: Debe subir la imagen del comprobante de pago y de IZIPAY.');
                                }
                            }
                        }
                    }

                    $basePath = public_path('assets/img-matriculado');
                    $clientFolder = $basePath . '/' . $request->cliente_id;

                    if (!File::exists($clientFolder)) {
                        File::makeDirectory($clientFolder, 0777, true, true);
                    }

                    if ($request->fullPayment == 0) {
                        $filenames = [];
                        foreach (['dniFront', 'dniRear', 'codeWaiver', 'izyPay', 'vaucher', 'additionalVoucher'] as $key) {
                            if ($request->hasFile($key)) {
                                $file = $request->file($key);
                                $extension = $file->getClientOriginalExtension();
                                $filename = strtolower(str_replace(['dniFront', 'dniRear', 'codeWaiver', 'izyPay', 'additionalVoucher'], ['dni-front', 'dni-rear', 'code-waiver', 'izy-pay', 'additional-voucher'], $key));
                                $filename = "{$filename}-{$request->cliente_id}.{$extension}";
                                $file->move($clientFolder, $filename);
                                $filenames[$key] = $filename;
                            }
                        }

                        if ($request->modalidad_pago !== null && $request->modalidad_pago !== "") {
                            DB::table('clientes')
                                ->where('id', $request->cliente_id)
                                ->update([
                                    'modalidad_pago' => $request->modalidad_pago,
                                    'updated_at' => Carbon::now(),
                                ]);
                        } else {
                            dd('Error: Debes seleccionar alguna modalidad de pago.');
                        }
                        $schoolName = $request->schoolName ?: null;
                        $completionDate = $request->completionDate ?: null;
                        if ($imgData) {
                            $updateData = array_merge((array)$imgData, [
                                'dni_front'  => $filenames['dniFront'] ?? null,
                                'dni_rear'   => $filenames['dniRear'] ?? null,
                                'code_waiver'   => $filenames['codeWaiver'] ?? null,
                                'izy_pay'    => $filenames['izyPay'] ?? null,
                                'vaucher'    => $filenames['vaucher'] ?? null,
                                'additional_voucher'    => $filenames['additionalVoucher'] ?? null,
                                'school_name' => $schoolName,
                                'completion_date' => $completionDate,
                                'updated_at' => Carbon::now(),
                            ]);
                            DB::table('client_registration_images')
                                ->where('id_client', $request->cliente_id)
                                ->update($updateData);
                        } else {
                            $updateData = [
                                'id_client'  => $request->cliente_id,
                                'dni_front'  => $filenames['dniFront'] ?? null,
                                'dni_rear'   => $filenames['dniRear'] ?? null,
                                'code_waiver'   => $filenames['codeWaiver'] ?? null,
                                'izy_pay'    => $filenames['izyPay'] ?? null,
                                'vaucher'    => $filenames['vaucher'] ?? null,
                                'additional_voucher'    => $filenames['additionalVoucher'] ?? null,
                                'school_name' => $schoolName,
                                'completion_date' => $completionDate,
                                'created_at' => Carbon::now(),
                            ];
                            DB::table('client_registration_images')->insert($updateData);
                        }
                    } else {
                        $filenames = [];
                        foreach (['dniFront', 'dniRear', 'codeWaiver', 'izyPay', 'vaucher'] as $key) {
                            if ($request->hasFile($key)) {
                                $file = $request->file($key);
                                $extension = $file->getClientOriginalExtension();
                                $filename = strtolower(str_replace(['dniFront', 'dniRear', 'codeWaiver', 'izyPay'], ['dni-front', 'dni-rear', 'code-waiver', 'izy-pay'], $key));
                                $filename = "{$filename}-{$request->cliente_id}.{$extension}";
                                $file->move($clientFolder, $filename);
                                $filenames[$key] = $filename;
                            }
                        }

                        if ($request->modalidad_pago !== null && $request->modalidad_pago !== "") {
                            DB::table('clientes')
                                ->where('id', $request->cliente_id)
                                ->update([
                                    'modalidad_pago' => $request->modalidad_pago,
                                    'updated_at' => Carbon::now(),
                                ]);
                        } else {
                            dd('Error: Debes seleccionar alguna modalidad de pago.');
                        }
                        $schoolName = $request->schoolName ?: null;
                        $completionDate = $request->completionDate ?: null;
                        if ($imgData) {
                            $updateData = array_merge((array)$imgData, [
                                'dni_front'  => $filenames['dniFront'] ?? null,
                                'dni_rear'   => $filenames['dniRear'] ?? null,
                                'code_waiver'   => $filenames['codeWaiver'] ?? null,
                                'izy_pay'    => $filenames['izyPay'] ?? null,
                                'vaucher'    => $filenames['vaucher'] ?? null,
                                'school_name' => $schoolName,
                                'completion_date' => $completionDate,
                                'updated_at' => Carbon::now(),
                            ]);
                            DB::table('client_registration_images')
                                ->where('id_client', $request->cliente_id)
                                ->update($updateData);
                        } else {
                            $updateData = [
                                'id_client'  => $request->cliente_id,
                                'dni_front'  => $filenames['dniFront'] ?? null,
                                'dni_rear'   => $filenames['dniRear'] ?? null,
                                'code_waiver'   => $filenames['codeWaiver'] ?? null,
                                'izy_pay'    => $filenames['izyPay'] ?? null,
                                'vaucher'    => $filenames['vaucher'] ?? null,
                                'school_name' => $schoolName,
                                'completion_date' => $completionDate,
                                'created_at' => Carbon::now(),
                            ];
                            DB::table('client_registration_images')->insert($updateData);
                        }
                    }
                }
            } catch (\Exception $e) {
                dd($e->getMessage());
            }



            if (!$validator->fails()) {
                $Seguimiento = ClienteSeguimiento::create($request->all());
                if ($Seguimiento) {
                    $status = true;
                    $Cliente = Cliente::find($request->cliente_id);
                    $Cliente->estado_id = $request->estado_id;
                    $Cliente->estado_detalle_id = $request->estado_detalle_id;
                    $Cliente->ultimo_contacto = Carbon::now();
                    if (in_array($request->estado_detalle_id, [App::$ESTADO_DETALLE_MATRICULADO, App::$ESTADO_DETALLE_TRASLADO])) {
                        $Cliente->direccion = $request->direccion;
                        $Cliente->turno_id = $request->turno_id;
                        $Cliente->sede_id = $request->sede_id;
                        $Cliente->local_id = $request->local_id;
                        $Cliente->horario_id = $request->horario_id;
                        $Cliente->tipo_operacion_id = $request->tipo_operacion_id;
                        $Cliente->nro_operacion = $request->nro_operacion;
                        $Cliente->monto = $request->monto;
                        $Cliente->nombre_titular = $request->nombre_titular;
                        $Cliente->codigo_alumno = $request->codigo_alumno;
                        $Cliente->promocion = $request->promocion;
                        $Cliente->observacion = $request->observacion;

                        if ($CarreraPresencial != null && $CarreraPresencial->semipresencial) {
                            $Cliente->presencial_sede_id = $request->presencial_sede_id;
                            $Cliente->presencial_turno_id = $request->presencial_turno_id;
                            $Cliente->presencial_horario_id = $request->presencial_horario_id;
                        }
                    } else if ($request->estado_detalle_id == App::$ESTADO_DETALLE_REINGRESO) {
                        $Cliente->codigo_reingreso = $request->codigo_reingreso;
                        $Cliente->semestre_termino_id = $request->semestre_termino_id;
                        $Cliente->ciclo_termino_id = $request->ciclo_termino_id;
                        $Cliente->semestre_inicio_id = $request->semestre_inicio_id;
                        $Cliente->ciclo_inicio_id = $request->ciclo_inicio_id;
                        $Cliente->mes = $request->mes;
                        $Cliente->cursos_jalados = $request->cursos_jalados;
                    } else if ($request->estado_id == App::$ESTADO_OTROS) {

                        $user = DB::table('users')->select('id')->where('profile_id', App::$PERFIL_RESTRINGIDO)->where('turno', true)->first();

                        $usersAllIds = DB::table('users')->whereNull('deleted_at')->where('profile_id', App::$PERFIL_RESTRINGIDO)
                            ->where('activo', true)->pluck('id')->toArray();

                        $userId = $user != null ? $user->id : DB::table('users')->select('id')->where('profile_id', App::$PERFIL_RESTRINGIDO)->first()->id;

                        $minId = array_search(min($usersAllIds), $usersAllIds);
                        $maxId = array_search(max($usersAllIds), $usersAllIds);

                        $userTurnId = array_search($userId, $usersAllIds) + 1;

                        if ($userTurnId > $maxId)
                            $userTurnId = $usersAllIds[$minId];
                        else
                            $userTurnId = $usersAllIds[$userTurnId];

                        $userNew = User::find($userTurnId);
                        if ($userNew != null) {
                            $userNew->turno = true;
                            if ($userNew->save()) {
                                $status = true;
                                if ($userNew->id != $userId) {
                                    $userAnterior = User::find($userId);
                                    $userAnterior->turno = false;
                                    if (!$userAnterior->save()) $status = false;
                                }
                            }
                        }

                        if ($status) $Cliente->user_id = $userTurnId;
                    }

                    $Cliente->save();
                }
            }

            if ($status) {

                $Notification = Notification::where('cliente_id', $Cliente->id)->where('estado', false)
                    ->orderby('id', 'desc')->first();
                if ($Notification != null) {
                    $Notification->estado = true;
                    $Notification->save();
                }

                if ($Seguimiento->fecha_accion_realizar != null && $Seguimiento->fecha_accion_realizar == Carbon::parse(Carbon::now())->format('Y-m-d')) {
                    $Notification = new Notification();
                    $Notification->cliente_id = $Cliente->id;
                    $Notification->cliente_seguimiento_id = $Seguimiento->id;
                    $Notification->estado = false;
                    $Notification->save();
                }

                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Exception $e) {
            DB::rollBack();
        }

        return response()->json(['Success' => $status, 'Errors' => $validator->errors()]);
    }

    public function storeSeguimientoAdicional(Request $request)
    {
        $status = false;
        $validator = null;

        try {

            DB::beginTransaction();

            $request->merge([
                'cliente_id' => $request->id,
            ]);

            $CarreraPresencial = Carrera::find($request->carrera_adicional_id);

            if ($CarreraPresencial != null && $CarreraPresencial->semipresencial) {
                $validator = Validator::make($request->all(), [
                    'modalidad_adicional_id' => 'required',
                    'carrera_adicional_id' => 'required',
                    'sede_adicional_id' => 'required',
                    'local_adicional_id' => 'required',
                    'turno_adicional_id' => 'required',
                    'horario_adicional_id' => 'required',
                    'presencial_adicional_sede_id' => 'required',
                    'presencial_adicional_turno_id' => 'required',
                    'presencial_adicional_horario_id' => 'required',
                    'tipo_operacion_adicional_id' => 'required',
                    'nro_operacion_adicional' => 'required|max:15',
                    'monto_adicional' => 'required|numeric',
                    'nombre_titular_adicional' => 'required',
                    'codigo_alumno_adicional' => 'required',
                    'promocion_adicional' => 'required',
                    'observacion_adicional' => 'required',
                ]);
            } else {
                $validator = Validator::make($request->all(), [
                    'modalidad_adicional_id' => 'required',
                    'carrera_adicional_id' => 'required',
                    'sede_adicional_id' => 'required',
                    'local_adicional_id' => 'required',
                    'turno_adicional_id' => 'required',
                    'horario_adicional_id' => 'required',
                    'tipo_operacion_adicional_id' => 'required',
                    'nro_operacion_adicional' => 'required|max:15',
                    'monto_adicional' => 'required|numeric',
                    'nombre_titular_adicional' => 'required',
                    'codigo_alumno_adicional' => 'required',
                    'promocion_adicional' => 'required',
                    'observacion_adicional' => 'required',
                ]);
            }

            if (!$validator->fails()) {
                if (
                    count(Cliente::where('id', $request->id)->where('modalidad_id', $request->modalidad_adicional_id)->where('carrera_id', $request->carrera_adicional_id)->get()) > 0 ||
                    count(ClienteMatricula::where('cliente_id', $request->id)->where('modalidad_adicional_id', $request->modalidad_adicional_id)->where('carrera_adicional_id', $request->carrera_adicional_id)->get()) > 0
                ) {
                    return response()->json(['Success' => $status, 'Message' => 'Ya se encuentra matriculado en ' . Modalidad::find($request->modalidad_adicional_id)->name . ' de ' . Carrera::find($request->carrera_adicional_id)->name]);
                }
                $seguimientoAdicional = ClienteMatricula::create($request->all());
                $newId = $seguimientoAdicional->id;
                try {
                    $imgData = DB::table('client_registration_images_additional')
                        ->where('id_client_additional', $newId)
                        ->first();

                    if ($request->tipo_operacion_adicional_id != 3) {
                        if (!$imgData && !$request->hasFile('vaucherAdditional')) {
                            dd('Error: Debe subir la imagen del comprobante de pagos.');
                        }
                    }
                    if ($request->modalidad_pago_adicional == 2) {
                        if ($request->tipo_operacion_adicional_id == 4 || $request->tipo_operacion_adicional_id == 5 || $request->tipo_operacion_adicional_id == 6) {
                            if (!$imgData) {
                                if (!$request->hasFile('vaucherAdditional') || !$request->hasFile('izyPayAdditional')) {
                                    dd('Error: Debe subir la imagen del comprobante de pago y de IZIPAY.');
                                }
                            }
                        }
                    }
                    $basePath = public_path('assets/img-matriculado-adicional');
                    $clientFolder = $basePath . '/' . $request->id;
                    if (!File::exists($clientFolder)) {
                        File::makeDirectory($clientFolder, 0777, true, true);
                    }
                    $filenames = [];
                    foreach (['dniFrontAdditional', 'dniRearAdditional', 'izyPayAdditional', 'vaucherAdditional'] as $key) {
                        if ($request->hasFile($key)) {
                            $file = $request->file($key);
                            $extension = $file->getClientOriginalExtension();
                            $filename = strtolower(str_replace(['dniFrontAdditional', 'dniRearAdditional', 'izyPayAdditional', 'vaucherAdditional'], ['dni-front', 'dni-rear', 'izy-pay', 'vaucher'], $key));
                            $filename = "{$filename}-{$newId}.{$extension}";
                            $file->move($clientFolder, $filename);
                            $filenames[$key] = $filename;
                        }
                    }
                    if ($request->modalidad_pago_adicional == null && $request->modalidad_pago_adicional == "") {
                        dd('Error: Debes seleccionar alguna modalidad de pago.');
                    }
                    $schoolName = $request->schoolNameAdditional ?: null;
                    $completionDate = $request->completionDateAdditional ?: null;
                    if ($imgData) {
                        $updateData = array_merge((array)$imgData, [
                            'dni_front_additional'  => $filenames['dniFrontAdditional'] ?? null,
                            'dni_rear_additional'   => $filenames['dniRearAdditional'] ?? null,
                            'izy_pay_additional'    => $filenames['izyPayAdditional'] ?? null,
                            'vaucher_additional'    => $filenames['vaucherAdditional'] ?? null,
                            'school_name_additional' => $schoolName,
                            'completion_date_additional' => $completionDate,
                            'updated_at' => Carbon::now(),
                        ]);
                        DB::table('client_registration_images_additional')
                            ->where('id_client_additional', $newId)
                            ->update($updateData);
                    } else {
                        $updateData = [
                            'id_client_additional'  => $newId,
                            'dni_front_additional'  => $filenames['dniFrontAdditional'] ?? null,
                            'dni_rear_additional'   => $filenames['dniRearAdditional'] ?? null,
                            'izy_pay_additional'    => $filenames['izyPayAdditional'] ?? null,
                            'vaucher_additional'    => $filenames['vaucherAdditional'] ?? null,
                            'school_name_additional' => $schoolName,
                            'completion_date_additional' => $completionDate,
                            'created_at' => Carbon::now(),
                        ];
                        DB::table('client_registration_images_additional')->insert($updateData);
                    }
                    DB::table('cliente_matriculas')->where('id', $newId)->update(["modalidad_pago_adicional" => $request->modalidad_pago_adicional]);
                } catch (\Exception $e) {
                    dd($e->getMessage());
                }
                if ($seguimientoAdicional) {
                    $status = true;
                    DB::commit();
                }
            }
        } catch (\Exception $e) {
            DB::rollBack();
        }

        return response()->json(['Success' => $status, 'Errors' => $validator != null ? $validator->errors() : []]);
    }

    public function list_filter_seguimiento(Request $request)
    {
        $Seguimientos = ClienteSeguimiento::with('acciones')->where('cliente_id', $request->id)
            ->with('users')
            ->with('clientes.users')->with('clientes')->with('clientes.turnos')->with('clientes.sedes')->with('clientes.carreras')->with('clientes.modalidades')->with('clientes.locales')
            ->with('clientes.horarios')->with('clientes.semestreInicio')->with('clientes.cicloInicio')
            ->with('estados')->with('estadoDetalle')->orderby('created_at', 'desc')->get();
        $imgData = DB::table('client_registration_images')
            ->where('id_client', $request->id)
            ->first();
        if (!$imgData) {
            $imgData = null;
        }
        return response()->json(['data' => $Seguimientos, 'imgData' => $imgData]);
    }

    public function list_filter_seguimiento_adicional(Request $request)
    {
        $SeguimientosAdicionales = ClienteMatricula::where('cliente_id', $request->id)
            ->with('clientes')->with('turnos')->with('sedes')->with('carreras')->with('tipoOperaciones')->with('locales')
            ->with('modalidades')->with('horarios')->orderby('created_at', 'desc')->get();
        $imgAdicionalesData = DB::table('client_registration_images_additional')
            ->whereIn('id_client_additional', $SeguimientosAdicionales->pluck('id')->toArray())
            ->get();
        if ($imgAdicionalesData->isEmpty()) {
            $imgAdicionalesData = null;
        }
        return response()->json(['data' => $SeguimientosAdicionales, 'imgAdicionalesData' => $imgAdicionalesData]);
    }

    public function partialViewExport()
    {
        $Estados =  !in_array(Auth::guard('web')->user()->profile_id, [App::$PERFIL_VENDEDOR, App::$PERFIL_PROVINCIA]) ? Estado::all() : Estado::where('id', '!=', App::$ESTADO_OTROS)->get();
        $Vendedores = User::whereIn('profile_id', [App::$PERFIL_VENDEDOR, App::$PERFIL_PROVINCIA, App::$PERFIL_RESTRINGIDO])->orderby('turno_id', 'asc')->get();
        $Modalidades = Modalidad::all();
        $Turnos = Turno::whereNotIn('id', [App::$TURNO_GLOABAL])->get();

        return view('auth.cliente._Exportar', ['Estados' => $Estados, 'Vendedores' => $Vendedores, 'Modalidades' => $Modalidades, 'Turnos' => $Turnos]);
    }

    public function exportExcel($fechaInicio, $fechaFinal, $estado, $vendedor, $modalidad, $carrera, $turno)
    {
        ExportLeadJob::dispatch(
            $fechaInicio,
            $fechaFinal,
            $estado,
            (in_array(Auth::guard('web')->user()->profile_id, [App::$PERFIL_ADMINISTRADOR, App::$PERFIL_CAJERO]) ? $vendedor : Auth::guard('web')->user()->id),
            $modalidad,
            $carrera,
            $turno,
            Auth::guard('web')->user()->id
        )->onQueue('high');

        return response()->json(['success' => true]);
    }

    public function resumenDiario()
    {
        $fechaInicio = Carbon::now()->format('d') >= 16 ? Carbon::now()->firstOfMonth()->addDay(15) : Carbon::now()->subMonth('1')->firstOfMonth()->addDay(15);
        $fechaFinal = Carbon::now()->format('d') >= 16 ? Carbon::now()->endOfMonth()->addDay(15) : Carbon::now()->subMonth('1')->endOfMonth()->addDay(15);

        return Excel::download(new ResumenDiario($fechaInicio, $fechaFinal, Carbon::now()->toDateString()), 'ReumenDiario.xlsx');
    }

    public function partialViewImport()
    {
        $Vendedor = Profile::whereIn('id', [App::$PERFIL_VENDEDOR, App::$PERFIL_RESTRINGIDO])->get();
        return view('auth.cliente.import.excel', ['Vendedor' => $Vendedor]);
    }

    public function importExcel(Request $request)
    {
        if ($request->file('import_archivo_id') == null || $request->file('import_archivo_id')->getClientOriginalExtension() != "xlsx") {
            return response()->json(['Success' => false, 'Error' => 'Por favor, seleccione un archivo xls válido.']);
        } else {
            $import = new ClientesImport($request->import_perfil_id, $this->obtenerAsesoresRecibeLeads());
            $import->import($request->file('import_archivo_id'));

            return response()->json(['Success' => count($import->getErrors()) > 0 ? false : true, 'Errors' => $import->getErrors()]);
        }
    }

    public function notifications()
    {
        $notifications = Notification::with('clientes')->with('cliente_seguimientos')->with('cliente_seguimientos.accionRealizar')
            ->where('estado', false)->whereHas('clientes', function ($q) {
                $q->where('user_id', Auth::guard('web')->user()->id);
            })->orWhere('user_id', Auth::guard('web')->user()->id)->orderBy('id', 'DESC')->get();

        return response()->json(['data' => $notifications]);
    }

    public function delete(Request $request)
    {
        $status = false;
        $entity = Cliente::find($request->id);
        $entity->deleted_modified_by = auth()->user()->id;
        $cleinteMatricula = DB::table('cliente_matriculas')
            ->where('cliente_id', $request->id)
            ->update(['deleted_at' => Carbon::now()]);
        //if($Cliente->save()){ $status = true;}
        if ($entity->delete() and $entity->save()) $status = true;
        return response()->json(['Success' => $status]);
    }

    public function consultar_reniec($data)
    {
        $token = 'apis-token-1.aTSI1U7KEuT-6bbbCguH-4Y8TI6KS73N';
        $dni = $data;
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.apis.net.pe/v1/dni?numero=' . $dni,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 2,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Referer: https://apis.net.pe/consulta-dni-api',
                'Authorization: Bearer ' . $token
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return response()->json($response);
    }

    public function uploadBoxImages(Request $request)
    {
        try {
            $imgData = DB::table('client_registration_images')
                ->where('id_client', $request->idClient)
                ->first();
            $basePath = public_path('assets/img-matriculado');
            $clientFolder = $basePath . '/' . $request->idClient;
            if (!File::exists($clientFolder)) {
                File::makeDirectory($clientFolder, 0777, true, true);
            }
            $filenames = [];
            foreach (['dniFront', 'dniRear', 'codeWaiver', 'izyPay', 'vaucher', 'additionalVoucher'] as $key) {
                if ($request->hasFile($key)) {
                    $file = $request->file($key);
                    $extension = $file->getClientOriginalExtension();
                    $filename = strtolower(str_replace(['dniFront', 'dniRear', 'codeWaiver', 'izyPay', 'additionalVoucher'], ['dni-front', 'dni-rear', 'code-waiver', 'izy-pay', 'additional-voucher'], $key));
                    $filename = "{$filename}-{$request->idClient}.{$extension}";
                    $file->move($clientFolder, $filename);
                    $filenames[$key] = $filename;
                }
            }
            $schoolName = $request->input('schoolNameUpdate') ?: null;
            $completionDate = $request->input('completionDateUpdate') ?: null;
            if ($imgData) {
                $updateData = array_merge((array)$imgData, [
                    'dni_front'  => $filenames['dniFront'] ?? $imgData->dni_front,
                    'dni_rear'   => $filenames['dniRear'] ?? $imgData->dni_rear,
                    'code_waiver'    => $filenames['codeWaiver'] ?? $imgData->code_waiver,
                    'izy_pay'    => $filenames['izyPay'] ?? $imgData->izy_pay,
                    'vaucher'    => $filenames['vaucher'] ?? $imgData->vaucher,
                    'additional_voucher'    => $filenames['additionalVoucher'] ?? $imgData->additional_voucher,
                    'school_name' => $schoolName,
                    'completion_date' => $completionDate,
                    'updated_at' => Carbon::now(),
                ]);
                DB::table('client_registration_images')
                    ->where('id_client', $request->idClient)
                    ->update($updateData);
            } else {
                DB::table('client_registration_images')->insert([
                    'id_client'  => $request->idClient,
                    'dni_front'  => $filenames['dniFront'] ?? null,
                    'dni_rear'   => $filenames['dniRear'] ?? null,
                    'code_waiver' => $filenames['codeWaiver'] ?? null,
                    'izy_pay'    => $filenames['izyPay'] ?? null,
                    'vaucher'    => $filenames['vaucher'] ?? null,
                    'additional_voucher'    => $filenames['additionalVoucher'] ?? null,
                    'school_name' => $schoolName,
                    'completion_date' => $completionDate,
                    'created_at' => Carbon::now(),
                ]);
            }
            $updateData = [];
            if ($request->dniFrontDelete == 1) {
                $updateData['dni_front'] = null;
            }
            if ($request->dniRearDelete == 1) {
                $updateData['dni_rear'] = null;
            }
            if ($request->codeWaiverDelete == 1) {
                $updateData['code_waiver'] = null;
            }
            if ($request->izyPayDelete == 1) {
                $updateData['izy_pay'] = null;
            }
            if ($request->vaucherDelete == 1) {
                $updateData['vaucher'] = null;
            }
            if ($request->additionalVoucherDelete == 1) {
                $updateData['additional_voucher'] = null;
            }
            if (!empty($updateData)) {
                $updateData['updated_at'] = Carbon::now();
                DB::table('client_registration_images')
                    ->where('id_client', $request->idClient)
                    ->update($updateData);
            }
            return response()->json([
                'success' => true,
                'message' => '¡Imágenes subidas correctamente!'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al subir las imágenes: ' . $e->getMessage()
            ], 500);
        }
    }
    public function uploadAdditionalBoxImages(Request $request)
    {
        try {
            $imgData = DB::table('client_registration_images_additional')
                ->where('id_client_additional', $request->idClientAdditional)
                ->first();
            $basePath = public_path('assets/img-matriculado-adicional');
            $clientFolder = $basePath . '/' . $request->idClient;
            if (!File::exists($clientFolder)) {
                File::makeDirectory($clientFolder, 0777, true, true);
            }
            $filenames = [];
            foreach (['dniFrontUpdate', 'dniRearUpdate', 'izyPayUpdate', 'vaucherUpdate'] as $key) {
                if ($request->hasFile($key)) {
                    $file = $request->file($key);
                    $extension = $file->getClientOriginalExtension();
                    $filename = strtolower(str_replace(['dniFrontUpdate', 'dniRearUpdate', 'izyPayUpdate', 'vaucherUpdate'], ['dni-front', 'dni-rear', 'izy-pay', 'vaucher'], $key));
                    $filename = "{$filename}-{$request->idClientAdditional}.{$extension}";
                    $file->move($clientFolder, $filename);
                    $filenames[$key] = $filename;
                }
            }
            if ($imgData) {
                $schoolName = $request->input('schoolNameUpdate') ?: $imgData->school_name_additional;
                $completionDate = $request->input('completionDateUpdate') ?: $imgData->completion_date_additional;
                $updateData = array_merge((array)$imgData, [
                    'dni_front_additional'  => $filenames['dniFrontUpdate'] ?? $imgData->dni_front_additional,
                    'dni_rear_additional'   => $filenames['dniRearUpdate'] ?? $imgData->dni_rear_additional,
                    'izy_pay_additional'    => $filenames['izyPayUpdate'] ?? $imgData->izy_pay_additional,
                    'vaucher_additional'    => $filenames['vaucherUpdate'] ?? $imgData->vaucher_additional,
                    'school_name_additional' => $schoolName,
                    'completion_date_additional' => $completionDate,
                    'updated_at' => Carbon::now(),
                ]);
                DB::table('client_registration_images_additional')
                    ->where('id_client_additional', $request->idClientAdditional)
                    ->update($updateData);
            } else {
                $schoolName = $request->input('schoolNameUpdate') ?: null;
                $completionDate = $request->input('completionDateUpdate') ?: null;
                DB::table('client_registration_images_additional')->insert([
                    'id_client_additional'  => $request->idClientAdditional,
                    'dni_front_additional'  => $filenames['dniFrontUpdate'] ?? null,
                    'dni_rear_additional'   => $filenames['dniRearUpdate'] ?? null,
                    'izy_pay_additional'    => $filenames['izyPayUpdate'] ?? null,
                    'vaucher_additional'    => $filenames['vaucherUpdate'] ?? null,
                    'school_name_additional' => $schoolName,
                    'completion_date_additional' => $completionDate,
                    'created_at' => Carbon::now(),
                ]);
            }
            $updateData = [];
            if ($request->dniFrontDelete == 1) {
                $updateData['dni_front_additional'] = null;
            }
            if ($request->dniRearDelete == 1) {
                $updateData['dni_rear_additional'] = null;
            }
            if ($request->izyPayDelete == 1) {
                $updateData['izy_pay_additional'] = null;
            }
            if ($request->vaucherDelete == 1) {
                $updateData['vaucher_additional'] = null;
            }
            if (!empty($updateData)) {
                $updateData['updated_at'] = Carbon::now();
                DB::table('client_registration_images_additional')
                    ->where('id_client_additional', $request->idClientAdditional)
                    ->update($updateData);
            }
            return response()->json([
                'success' => true,
                'message' => '¡Imágenes subidas correctamente!'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al subir las imágenes: ' . $e->getMessage()
            ], 500);
        }
    }
    public function getDataClient(Request $request)
    {
        $imgData = DB::table('client_registration_images')
            ->where('id_client', $request->idClient)
            ->first();
        return response()->json([
            'success' => true,
            'data' => $imgData
        ], 200);
    }
    public function notificationsTracking()
    {
        $userLogin = Auth::user();
        if (!$userLogin) {
            return response()->json(['error' => 'No autenticado'], 401);
        }
        $query = DB::table('notifications')
            ->join('users', 'notifications.user_id', '=', 'users.id')
            ->join('clientes', 'notifications.cliente_id', '=', 'clientes.id')
            ->select(
                'notifications.cliente_id as idNotification',
                'clientes.dni as dniClient',
                'users.name as nameAdvisor',
                'users.last_name as lastNameAdvisor',
                'users.id as idAdvisor',
                'notifications.box_tracking as boxTracking',
                'notifications.created_at as fecha'
            )
            ->where('notifications.box_tracking', 1)
            ->where('notifications.estado', false);

        if ($userLogin->profile_id == 2) {
            $query->where('notifications.user_id', $userLogin->id);
        }
        $query1 = DB::table('notifications')
            ->join('users', 'notifications.user_id', '=', 'users.id')
            ->join('cliente_matriculas', 'notifications.cliente_id', '=', 'cliente_matriculas.id')
            ->join('clientes', 'cliente_matriculas.cliente_id', '=', 'clientes.id')
            ->select(
                'notifications.cliente_id as idNotification',
                'clientes.dni as dniClient',
                'users.name as nameAdvisor',
                'users.last_name as lastNameAdvisor',
                'users.id as idAdvisor',
                'notifications.box_tracking as boxTracking',
                'notifications.created_at as fecha'
            )
            ->where('notifications.box_tracking', 2)
            ->where('notifications.estado', false);
        if ($userLogin->profile_id == 2) {
            $query1->where('notifications.user_id', $userLogin->id);
        }
        $notificationsData = $query
            ->unionAll($query1)
            ->orderBy('fecha', 'desc')
            ->get();

        return response()->json([
            'data' => $notificationsData
        ]);
    }
    public function seeObservation($id)
    {
        // API para actualizar seguimiento
        $userLogin = Auth::user();
        // Llamada al API externa
        $url = "https://seguimiento.ialmarketing.edu.pe/api/bring-follow-up";
        $data = [
            'cliente_id' => $id,
        ];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $responseData = json_decode($response, true);
        if ($userLogin->profile_id == 2 && $responseData['followUpData']['state_adviser'] == 1) {
            $url1 = "https://seguimiento.ialmarketing.edu.pe/api/advisor-reviewed";
            $data1 = [
                'cliente_id' => $id,
            ];
            $ch1 = curl_init($url1);
            curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch1, CURLOPT_POST, true);
            curl_setopt($ch1, CURLOPT_POSTFIELDS, http_build_query($data1));
            curl_setopt($ch1, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded'
            ]);
            $response1 = curl_exec($ch1);
            $httpCode = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
            curl_close($ch1);
            $responseData1 = json_decode($response1, true);
        }
        $responseData['imgData'] = DB::table('clientes')
            ->join('users', 'clientes.user_id', '=', 'users.id')
            ->leftJoin('client_registration_images', 'clientes.id', '=', 'client_registration_images.id_client')
            ->select(
                'clientes.id as idUnico',
                'clientes.dni as dniClient',
                'clientes.apellido_paterno as apellidoPaternoClient',
                'clientes.apellido_materno as apellidoMaternoClient',
                'clientes.nombres as nombresClient',
                'clientes.fecha_nacimiento as dateOfBirth',
                'clientes.email as emailClient',
                'clientes.direccion as addressClient',
                'clientes.celular as phoneClient',
                'clientes.nombre_titular as nameTitular',
                'clientes.tipo_operacion_id as idTipoOperacionAdicional',
                'client_registration_images.school_name as schoolName',
                'client_registration_images.completion_date as completionDate',
                DB::raw('CONCAT(users.last_name, " ", users.name) as usersAsesor'),
                'users.id as idAdvisor',
            )
            ->where('clientes.estado_id', 4)
            ->where('clientes.estado_detalle_id', 8)
            ->whereNull('clientes.deleted_at')
            ->where('clientes.id', $id)
            ->first();
        $responseData['tipoOperaciones'] = DB::table('tipo_operacions')->whereNull('deleted_at')->get();
        return view('auth.cliente.see-observation')->with('responseData', $responseData);
    }
    public function storeSeeObservation(Request $request)
    {
        try {
            DB::beginTransaction();
            $userLogin = Auth::user();
            $lastName = trim($request->paternalSurname . ' ' . $request->maternalSurname);

            // Actualizar datos del cliente
            $updateData = [
                'nombres' => $request->name,
                'apellidos' => $lastName,
                'apellido_paterno' => $request->paternalSurname,
                'apellido_materno' => $request->maternalSurname,
                'email' => $request->email,
                'dni' => $request->dni,
                'celular' => $request->celular,
                'whatsapp' => $request->celular,
                'fecha_nacimiento' => $request->date,
                'direccion' => $request->direction,
                'nombre_titular' => $request->nameHolder,
                'tipo_operacion_id' => $request->typeOfOperation,
                'updated_at' => Carbon::now(),
                'updated_modified_by' => $userLogin->id,
            ];

            Cliente::where('id', $request->idClient)->update($updateData);

            // Subida de imágenes
            $imgData = DB::table('client_registration_images')
                ->where('id_client', $request->idClient)
                ->first();

            $basePath = public_path('assets/img-matriculado');
            $clientFolder = $basePath . '/' . $request->idClient;

            if (!File::exists($clientFolder)) {
                File::makeDirectory($clientFolder, 0777, true, true);
            }

            $updateImgData = [];
            $fileFields = [
                'dniFrontUpdate' => 'dni_front',
                'dniRearUpdate' => 'dni_rear',
                'codeWaiverUpdate' => 'code_waiver',
                'izyPayUpdate' => 'izy_pay',
                'vaucherUpdate' => 'vaucher',
                'vaucherAdditionalUpdate' => 'additional_voucher'
            ];

            foreach ($fileFields as $requestKey => $dbColumn) {
                if ($request->hasFile($requestKey)) {
                    $file = $request->file($requestKey);
                    $extension = $file->getClientOriginalExtension();
                    $filename = str_replace('_', '-', $dbColumn) . '-' . $request->idClient . '.' . $extension;
                    $file->move($clientFolder, $filename);
                    $updateImgData[$dbColumn] = $filename;
                }
            }

            $schoolName = $request->schoolNameUpdate;
            $completionDate = $request->completionDateUpdate;

            if (!empty($updateImgData) || $schoolName || $completionDate) {
                $commonData = [
                    'school_name' => $schoolName,
                    'completion_date' => $completionDate,
                    'updated_at' => Carbon::now(),
                ];

                if ($imgData) {
                    DB::table('client_registration_images')
                        ->where('id_client', $request->idClient)
                        ->update(array_merge((array) $imgData, $updateImgData, $commonData));
                } else {
                    DB::table('client_registration_images')->insert(array_merge([
                        'id_client' => $request->idClient,
                        'created_at' => Carbon::now(),
                    ], $updateImgData, $commonData));
                }
            }

            // Llamar API de seguimiento
            $responseApi = $this->actualizarSeguimientoAPI($request->idClient);

            if ($responseApi['success'] === true) {
                $updated = DB::table('notifications')
                    ->where('cliente_id', $request->idClient)
                    ->where('estado', 0)
                    ->whereNull('deleted_at')
                    ->update([
                        'estado' => 1,
                        'updated_at' => Carbon::now()
                    ]);

                if ($updated === 0) {
                    DB::rollBack();
                    return redirect()
                        ->back()
                        ->withErrors(['No se encontraron notificaciones pendientes para actualizar.'])
                        ->withInput();
                }

                DB::commit();
                return redirect()
                    ->back()
                    ->with('success', '¡Imágenes subidas y seguimiento actualizado correctamente!');
            } else {
                DB::rollBack();
                return redirect()
                    ->back()
                    ->withErrors([$responseApi['message'] => $responseApi['error']])
                    ->withInput();
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->withErrors(['Error inesperado' => $e->getMessage()])
                ->withInput();
        }
    }
    private function actualizarSeguimientoAPI($clienteId)
    {
        $url = "https://seguimiento.ialmarketing.edu.pe/api/update-tracking";
        $data = [
            'cliente_id' => $clienteId,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);
        sleep(1);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return [
                'success' => false,
                'message' => 'Error en la solicitud cURL',
                'error' => $error
            ];
        }

        $responseData = json_decode($response, true);

        if ($httpCode !== 200 || $responseData === null) {
            return [
                'success' => false,
                'message' => 'Error en la API de seguimiento',
                'error' => $responseData ?? 'Respuesta no válida'
            ];
        }

        return [
            'success' => true,
            'data' => $responseData
        ];
    }
    //Adicional
    public function seeObservationAdditional($id)
    {
        // API para actualizar seguimiento
        $userLogin = Auth::user();
        // Llamada al API externa
        $url = "https://seguimiento.ialmarketing.edu.pe/api/bring-follow-up-additional";
        $data = [
            'cliente_id_additional' => $id,
        ];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $responseData = json_decode($response, true);
        if ($userLogin->profile_id == 2 && $responseData['followUpData']['state_adviser'] == 1) {
            $url1 = "https://seguimiento.ialmarketing.edu.pe/api/advisor-reviewed-additional";
            $data1 = [
                'cliente_id_additional' => $id,
            ];
            $ch1 = curl_init($url1);
            curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch1, CURLOPT_POST, true);
            curl_setopt($ch1, CURLOPT_POSTFIELDS, http_build_query($data1));
            curl_setopt($ch1, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded'
            ]);
            $response1 = curl_exec($ch1);
            $httpCode = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
            curl_close($ch1);
            $responseData1 = json_decode($response1, true);
        }
        $responseData['imgData'] = DB::table('cliente_matriculas')
            ->join('clientes', 'cliente_matriculas.cliente_id', '=', 'clientes.id')
            ->join('users', 'clientes.user_id', '=', 'users.id')
            ->leftJoin('client_registration_images_additional', 'cliente_matriculas.id', '=', 'client_registration_images_additional.id_client_additional')
            ->select(
                'cliente_matriculas.id as idUnico',
                'clientes.id as idClient',
                'clientes.dni as dniClient',
                'clientes.apellido_paterno as apellidoPaternoClient',
                'clientes.apellido_materno as apellidoMaternoClient',
                'clientes.nombres as nombresClient',
                'clientes.fecha_nacimiento as dateOfBirth',
                'clientes.email as emailClient',
                'clientes.direccion as addressClient',
                'clientes.celular as phoneClient',
                'cliente_matriculas.nombre_titular_adicional as nameTitular',
                'cliente_matriculas.tipo_operacion_adicional_id  as idTipoOperacionAdicional',
                'client_registration_images_additional.school_name_additional as schoolName',
                'client_registration_images_additional.completion_date_additional as completionDate',
                DB::raw('CONCAT(users.last_name, " ", users.name) as usersAsesor'),
                'users.id as idAdvisor'
            )
            ->whereNull('cliente_matriculas.deleted_at')
            ->where('cliente_matriculas.id', $id)
            ->first();
        $responseData['tipoOperaciones'] = DB::table('tipo_operacions')->whereNull('deleted_at')->get();
        return view('auth.cliente.see-observation-additional')->with('responseData', $responseData);
    }
    public function storeSeeObservationAdditional(Request $request)
    {
        try {
            DB::beginTransaction();
            $userLogin = Auth::user();
            $lastName = trim($request->paternalSurname . ' ' . $request->maternalSurname);

            // Actualizar datos del cliente
            $updateData = [
                'nombres' => $request->name,
                'apellidos' => $lastName,
                'apellido_paterno' => $request->paternalSurname,
                'apellido_materno' => $request->maternalSurname,
                'email' => $request->email,
                'dni' => $request->dni,
                'celular' => $request->celular,
                'whatsapp' => $request->celular,
                'fecha_nacimiento' => $request->date,
                'direccion' => $request->direction,
                'updated_at' => Carbon::now(),
                'updated_modified_by' => $userLogin->id,
            ];

            $updateMatriculaAdicional = [
                'nombre_titular_adicional' => $request->nameHolder,
                'tipo_operacion_adicional_id' => $request->typeOfOperation,
                'updated_at' => Carbon::now()
            ];

            Cliente::where('id', $request->idClient)->update($updateData);

            ClienteMatricula::where('id', $request->idClientAdditional)->update($updateMatriculaAdicional);

            // Subida de imágenes
            $imgData = DB::table('client_registration_images_additional')
                ->where('id_client_additional', $request->idClientAdditional)
                ->first();

            $basePath = public_path('assets/img-matriculado-adicional');
            $clientFolder = $basePath . '/' . $request->idClientAdditional;

            if (!File::exists($clientFolder)) {
                File::makeDirectory($clientFolder, 0777, true, true);
            }

            $updateImgData = [];
            $fileFields = [
                'dniFrontUpdate' => 'dni_front',
                'dniRearUpdate' => 'dni_rear',
                'izyPayUpdate' => 'izy_pay',
                'vaucherUpdate' => 'vaucher'
            ];

            foreach ($fileFields as $requestKey => $dbColumn) {
                if ($request->hasFile($requestKey)) {
                    $file = $request->file($requestKey);
                    $extension = $file->getClientOriginalExtension();
                    $filename = $dbColumn . '-' . $request->idClientAdditional . '.' . $extension;
                    $file->move($clientFolder, $filename);
                    $updateImgData[$dbColumn . '_additional'] = $filename;
                }
            }

            $schoolName = $request->schoolNameUpdate;
            $completionDate = $request->completionDateUpdate;

            if (!empty($updateImgData) || $schoolName || $completionDate) {
                $commonData = [
                    'school_name_additional' => $schoolName,
                    'completion_date_additional' => $completionDate,
                    'updated_at' => Carbon::now(),
                ];

                if ($imgData) {
                    DB::table('client_registration_images_additional')
                        ->where('id_client_additional', $request->idClientAdditional)
                        ->update(array_merge((array) $imgData, $updateImgData, $commonData));
                } else {
                    DB::table('client_registration_images_additional')->insert(array_merge([
                        'id_client_additional' => $request->idClientAdditional,
                        'created_at' => Carbon::now(),
                    ], $updateImgData, $commonData));
                }
            }

            // Llamar API de seguimiento
            $responseApi = $this->actualizarSeguimientoAPIAdditional($request->idClientAdditional);

            if ($responseApi['success'] === true) {
                $updated = DB::table('notifications')
                    ->where('cliente_id', $request->idClientAdditional)
                    ->where('estado', 0)
                    ->whereNull('deleted_at')
                    ->update([
                        'estado' => 1,
                        'updated_at' => Carbon::now()
                    ]);

                if ($updated === 0) {
                    DB::rollBack();
                    return redirect()
                        ->back()
                        ->withErrors(['No se encontraron notificaciones pendientes para actualizar.'])
                        ->withInput();
                }

                DB::commit();
                return redirect()
                    ->back()
                    ->with('success', '¡Imágenes subidas y seguimiento actualizado correctamente!');
            } else {
                DB::rollBack();
                return redirect()
                    ->back()
                    ->withErrors([$responseApi['message'] => $responseApi['error']])
                    ->withInput();
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->withErrors(['Error inesperado' => $e->getMessage()])
                ->withInput();
        }
    }
    public function actualizarSeguimientoAPIAdditional($id)
    {
        $url = "https://seguimiento.ialmarketing.edu.pe/api/update-tracking-additional";
        $data = [
            'cliente_id' => $id,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);
        sleep(1);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return [
                'success' => false,
                'message' => 'Error en la solicitud cURL',
                'error' => $error
            ];
        }

        $responseData = json_decode($response, true);

        if ($httpCode !== 200 || $responseData === null) {
            return [
                'success' => false,
                'message' => 'Error en la API de seguimiento',
                'error' => $responseData ?? 'Respuesta no válida'
            ];
        }

        return [
            'success' => true,
            'data' => $responseData
        ];
    }
}

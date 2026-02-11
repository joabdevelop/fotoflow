unit uMediaHashService;

interface

uses
  Winapi.Windows, System.SysUtils, System.Classes, System.SyncObjs, System.IOUtils,
  System.Generics.Collections, System.Threading, Vcl.SvcMgr, Vcl.Graphics,
  Winapi.ActiveX, Vcl.Imaging.jpeg, Vcl.Imaging.pngimage,
  // FireDAC
  FireDAC.Stan.Intf, FireDAC.Stan.Def, FireDAC.Stan.Pool, FireDAC.Stan.Async,
  FireDAC.Phys, FireDAC.Phys.Intf, FireDAC.Comp.Client,
  FireDAC.Phys.PG, FireDAC.Phys.PGDef, FireDAC.DApt,
  FireDAC.Stan.Option, FireDAC.Stan.Error, FireDAC.UI.Intf, FireDAC.VCLUI.Wait,
  Data.DB,
  // Unidades do Projeto
  uMediaConfig, uMediaDatabase, uMediaFileMover, uMediaHash;

const
  MAX_WORKERS = 4;

type
  TMediaHashService = class(TService)
    procedure ServiceStart(Sender: TService; var Started: Boolean);
    procedure ServiceExecute(Sender: TService);
    procedure ServiceStop(Sender: TService; var Stopped: Boolean);
  private
    { Componentes de Conexão criados dinamicamente para evitar erros de DFM }
    FFDConnection: TFDConnection;
    FPGDriverLink: TFDPhysPgDriverLink;

    FQueue: TThreadedQueue<string>;
    FKnownFiles: TDictionary<string, Boolean>;
    FCritical: TCriticalSection;

    procedure InitDatabase;
    procedure WatchFolder;
    procedure StartWorkers;
    procedure ProcessFile(const AFile: string);
    procedure LogEvent(const Msg: string; EventType: Word = EVENTLOG_INFORMATION_TYPE);
    function ParseFileName(const AFullFilePath: string): TImageData;
    function LoadImageToBitmap(const AFileName: string): TBitmap;
    procedure ConfigurarPostgres;
    function IsVideo(const AExtension: string): Boolean;
  public
    function GetServiceController: TServiceController; override;
    constructor Create(AOwner: TComponent); override;
    destructor Destroy; override;
  end;

var
  MediaHashService: TMediaHashService;

implementation

{$R *.dfm}

procedure ServiceController(CtrlCode: DWord); stdcall;
begin
  MediaHashService.Controller(CtrlCode);
end;

function TMediaHashService.GetServiceController: TServiceController;
begin
  Result := ServiceController;
end;

{ TMediaHashService }

constructor TMediaHashService.Create(AOwner: TComponent);
begin
  inherited Create(AOwner);

  // Criar componentes de acesso a dados dinamicamente
  FPGDriverLink := TFDPhysPgDriverLink.Create(Self);
  FFDConnection := TFDConnection.Create(Self);
  FFDConnection.LoginPrompt := False;

  FKnownFiles := TDictionary<string, Boolean>.Create;
  FCritical := TCriticalSection.Create;
  FQueue := TThreadedQueue<string>.Create(1000, 10, 100);
end;

destructor TMediaHashService.Destroy;
begin
  FQueue.Free;
  FKnownFiles.Free;
  FCritical.Free;
  inherited;
end;

procedure TMediaHashService.ConfigurarPostgres;
var
  PgBin: string;
begin
  // Caminho configurado para Laragon ou Instalação Padrão
  PgBin := 'C:\laragon\bin\postgresql\postgresql-14.5-1\bin\';

  if TDirectory.Exists(PgBin) then
  begin
    SetDllDirectory(PChar(PgBin));
    FPGDriverLink.VendorLib := TPath.Combine(PgBin, 'libpq.dll');
  end;
end;

procedure TMediaHashService.InitDatabase;
begin
  try
    FFDConnection.Connected := False;
    FFDConnection.Params.Clear;
    FFDConnection.Params.Add('DriverID=PG');
    FFDConnection.Params.Add('Database=' + TMediaConfig.DBName);
    FFDConnection.Params.Add('User_Name=' + TMediaConfig.DBUser);
    FFDConnection.Params.Add('Password=' + TMediaConfig.DBPassword);
    FFDConnection.Params.Add('Server=' + TMediaConfig.DBHost);
    FFDConnection.Params.Add('Port=' + TMediaConfig.DBPort);
    FFDConnection.Connected := True;
    LogEvent('✅ Conexão PostgreSQL estabelecida com sucesso.');
  except
    on E: Exception do
      LogEvent('❌ Erro ao ligar ao PostgreSQL: ' + E.Message, EVENTLOG_ERROR_TYPE);
  end;
end;

function TMediaHashService.IsVideo(const AExtension: string): Boolean;
var
  Ext: string;
begin
  Ext := AExtension.ToLower.Replace('.', '');
  Result := (Ext = 'mp4') or (Ext = 'avi') or (Ext = 'mkv') or (Ext = 'mov') or (Ext = 'wmv');
end;

procedure TMediaHashService.ProcessFile(const AFile: string);
var
  LocalConn: TFDConnection;
  DB: TMediaDatabase;
  ImgData, Analysis: TImageData;
  DestPath: string;
  Bmp: TBitmap;
begin
  try
    if not FileExists(AFile) then Exit;

    ImgData := ParseFileName(AFile);
    ImgData.Hash := TMediaHash.GetMD5(AFile);
    ImgData.FileSize := TFile.GetSize(AFile);
    ImgData.FileExtension := TPath.GetExtension(AFile).ToLower.Replace('.', '');
    ImgData.PHash := '';

    if IsVideo(ImgData.FileExtension) then
    begin
      ImgData.MimeType := 'video/' + ImgData.FileExtension;
    end
    else
    begin
      ImgData.MimeType := 'image/' + ImgData.FileExtension;
      Bmp := LoadImageToBitmap(AFile);
      try
        if Assigned(Bmp) and (not Bmp.Empty) then
          ImgData.PHash := TMediaHash.GetPerceptualHash(Bmp);
      finally
        Bmp.Free;
      end;
    end;

    // Conexão thread-safe (Local)
    LocalConn := TFDConnection.Create(nil);
    try
      LocalConn.Params.Assign(FFDConnection.Params);
      LocalConn.LoginPrompt := False;
      LocalConn.Connected := True;

      DB := TMediaDatabase.Create(LocalConn, nil);
      try
        // Passagem do BestDist carregado do .ini
        Analysis := DB.AnalyzeFileData(AFile, ImgData.Hash, ImgData.PHash, TMediaConfig.BestDist);

        ImgData.Result := Analysis.Result;
        ImgData.Score := Analysis.Score;
        ImgData.SimilarId := Analysis.SimilarId;

        if TMediaFileMover.MoveByResult(AFile, ImgData.Result, TMediaConfig.LibraryPath, TMediaConfig.DuplicatesPath, DestPath) then
        begin
          DB.SaveToDatabase(DestPath, ImgData);
        end;
      finally
        DB.Free;
      end;
    finally
      LocalConn.Free;
    end;

  except
    on E: Exception do
      LogEvent('Erro ao processar ' + ExtractFileName(AFile) + ': ' + E.Message, EVENTLOG_ERROR_TYPE);
  end;

  FCritical.Enter;
  try
    FKnownFiles.Remove(AFile);
  finally
    FCritical.Leave;
  end;
end;

function TMediaHashService.LoadImageToBitmap(const AFileName: string): TBitmap;
var
  JPegImg: TJPEGImage;
  PngImg: TPngImage;
  Ext: string;
begin
  Result := TBitmap.Create;
  Ext := TPath.GetExtension(AFileName).ToLower;
  try
    if (Ext = '.jpg') or (Ext = '.jpeg') then
    begin
      JPegImg := TJPEGImage.Create;
      try
        JPegImg.LoadFromFile(AFileName);
        Result.Assign(JPegImg);
      finally
        JPegImg.Free;
      end;
    end
    else if Ext = '.png' then
    begin
      PngImg := TPngImage.Create;
      try
        PngImg.LoadFromFile(AFileName);
        Result.Assign(PngImg);
      finally
        PngImg.Free;
      end;
    end
    else
      Result.LoadFromFile(AFileName);

    if Assigned(Result) then
      Result.PixelFormat := pf24bit;
  except
    FreeAndNil(Result);
  end;
end;

function TMediaHashService.ParseFileName(const AFullFilePath: string): TImageData;
var
  Parts: TArray<string>;
begin
  Result := Default(TImageData);
  Parts := TPath.GetFileNameWithoutExtension(AFullFilePath).Split(['-']);
  if Length(Parts) >= 5 then
  begin
    Result.PhotoGallery := Parts[0];
    Result.Origin       := Parts[1];
    Result.PhotoName     := Parts[2];
    Result.Description   := Parts[3].Replace('_', ' ');
  end;
  Result.FileExtension := TPath.GetExtension(AFullFilePath);
end;

procedure TMediaHashService.ServiceStart(Sender: TService; var Started: Boolean);
begin
  try
    TMediaConfig.Load;
    ConfigurarPostgres;
    InitDatabase;
    Started := True;
    LogEvent('🚀 Serviço MediaHash iniciado. BestDist: ' + TMediaConfig.BestDist.ToString);
  except
    on E: Exception do
    begin
      LogEvent('Falha no arranque do serviço: ' + E.Message, EVENTLOG_ERROR_TYPE);
      Started := False;
    end;
  end;
end;

procedure TMediaHashService.ServiceExecute(Sender: TService);
begin
  StartWorkers;

  TTask.Run(
    procedure
    begin
      WatchFolder;
    end
  );

  while not Terminated do
  begin
    ServiceThread.ProcessRequests(False);
    Sleep(500);
  end;
end;


procedure TMediaHashService.ServiceStop(Sender: TService; var Stopped: Boolean);
begin
  Stopped := True;
  LogEvent('🛑 Serviço MediaHash parado.');
end;

procedure TMediaHashService.StartWorkers;
var
  I: Integer;
begin
  for I := 1 to MAX_WORKERS do
    TTask.Run(procedure
      var FileName: string;
      begin
        CoInitializeEx(nil, COINIT_MULTITHREADED);
        try
          while not Terminated do
            if FQueue.PopItem(FileName) = wrSignaled then
              ProcessFile(FileName)
            else
              Sleep(100);
        finally
          CoUninitialize;
        end;
      end);
end;

procedure TMediaHashService.WatchFolder;
var
  FileName: string;
begin
  while not Terminated do
  begin
    if TDirectory.Exists(TMediaConfig.WatchPath) then
    begin
      for FileName in TDirectory.GetFiles(TMediaConfig.WatchPath) do
      begin
        // Ignorar ficheiros de sistema
        if (FileName.ToLower.EndsWith('.db')) or (FileName.ToLower.EndsWith('.ini')) then
          Continue;

        FCritical.Enter;
        try
          if FKnownFiles.ContainsKey(FileName) then Continue;
          FKnownFiles.Add(FileName, True);
        finally
          FCritical.Leave;
        end;

        FQueue.PushItem(FileName);
      end;
    end;
    Sleep(3000);
  end;
end;

procedure TMediaHashService.LogEvent(const Msg: string; EventType: Word);
begin
  try
    Self.LogMessage(Msg, EventType, 0, 0);
  except
  end;
end;

end.
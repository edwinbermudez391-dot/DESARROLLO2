"""Empaqueta código y evidencia de entrega; excluye credenciales, datos runtime y repositorio Git."""
from pathlib import Path
import hashlib, json, zipfile
ROOT = Path(__file__).resolve().parents[1]
DIRECTORIES = {'app', 'config', 'core', 'data', 'docker', 'docs', 'public', 'scripts', 'tests', '.github'}
ROOT_FILES = {'.dockerignore', '.gitignore', '.htaccess', 'compose.yaml', 'readme.txt', 'README.md',
              'DATABASE_MODELO_ENTREGA_1.sql', 'SEEDERS_ENTREGA_1.sql'}
def source_files():
    result=[]
    for path in ROOT.rglob('*'):
        if not path.is_file(): continue
        relative=path.relative_to(ROOT)
        if any(part in {'.git','.sessions','__pycache__','node_modules'} for part in relative.parts):continue
        if relative.parts[:2]==('docs','evidencias') and relative.suffix in {'.jpg','.png'}:continue
        if relative.as_posix() in {'docs/HISTORICO_INTEGRACION_ENTREGA_1.md','app/Views/test_login.html'}:continue
        if relative.name=='.env' or relative.suffix in {'.log','.pyc'}:continue
        if (len(relative.parts)==1 and relative.name in ROOT_FILES) or relative.parts[0] in DIRECTORIES:
            result.append(path)
    return sorted(result,key=lambda p:p.relative_to(ROOT).as_posix())
if __name__=='__main__':
    output=ROOT/'output'/'entrega';output.mkdir(parents=True,exist_ok=True)
    files=source_files();manifest={p.relative_to(ROOT).as_posix():hashlib.sha256(p.read_bytes()).hexdigest() for p in files}
    archive=output/'Encuestas-Cali-Desarrollo-II.zip'
    with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED) as z:
        for p in files:z.write(p,p.relative_to(ROOT).as_posix())
        z.writestr('SHA256SUMS.json',json.dumps(manifest,ensure_ascii=False,indent=2))
    print(f'{archive}\n{len(files)} archivos; {archive.stat().st_size} bytes')

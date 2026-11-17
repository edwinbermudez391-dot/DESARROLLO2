"""Genera configuración privada de desarrollo. Nunca sobrescribe un .env existente."""
from pathlib import Path
import secrets
path=Path(__file__).resolve().parents[1]/'.env'
if path.exists():
    print('Se conserva el archivo .env existente.')
else:
    path.write_text('DB_PASSWORD='+secrets.token_urlsafe(32)+'\nDEMO_PASSWORD='+secrets.token_urlsafe(24)+'\n',encoding='utf-8')
    print('Configuración privada creada en .env; las claves no se publican ni se imprimen.')

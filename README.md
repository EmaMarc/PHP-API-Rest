Seminario de PHP, React, y API Rest
===================================


## se modifico la zona horaria  en docker-compose.yml


## Configuración inicial

1. Crear archivo `.env` a partir de `.env.dist`

```bash
cp .env.dist .env
```

2. Crear volumen para la base de datos

```bash
docker volume create seminariophp
```

donde *seminariophp* es el valor de la variable `DB_VOLUME`

## Iniciar servicios

```bash
docker compose up -d
```

## Terminar servicios ----------------


### elimina los datos de la DB
```bash
docker compose down -v
```

### conserv los datos de la DB
```bash
docker compose down 
```

## Eliminar base de datos

```bash
docker volume rm seminariophp
```

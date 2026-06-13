## Deployment:Dockerization, GitHub CD, VPS setup Workflow

1) Create cd.yaml in .github/workflow
2) Create VPS_PASSWORD,VPS_HOST,DOCKER_USERNAME,DOCKER_PASSWORD secrets in 
Settings → Secrets and variables → Actions → Repository secret
3) added docker-compose.yaml, docker-entrypoint,Dockerfile in local project.
4) create .env,.env.docker, app & docker-compose.yml in vps folder.
5) push code & run github workflow.
6) 


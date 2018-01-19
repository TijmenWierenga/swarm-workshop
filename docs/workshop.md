# Prerequisites
* Build images with `docker-compose build` with predefined machine
* Add `devcoin-api.dev-lab.io` and `devcoin.dev-lab.io` to `HOSTS` file

# Welcome
Today I'll explain the basics of Docker Swarm to you guys.
We'll start by comparing Docker Compose and Docker Swarm and 
we'll move slowly from a small stack on a single node to a more robust
scalable cluster.

We'll be playing with the brand new DevCoin stack and our goal is to
mine as many coins as we can by utilizing the power of Docker Swarm.

The stack consists of:
* Miner: ReactPHP application
* Redis: To store the mined coins
* WebUI: NodeJS application using VueJS and Tailwind
* API: ReactPHP HTTP Server
* Reverse proxy: Traefik

![Stack setup](/images/DevCoin.png)

# Introduction: Docker Compose versus Docker Swarm

* docker-compose works only on a **single** node.
* Swarm is suitable for clustering, thus working on **multiple** nodes

```bash
docker-compose up -d
```

As you can see this won't work, because of the networks. Since we've specifically
described our networks, we need to create them first.

First, let's create our `default` network. With Docker Compose, we utilize the
`bridge` driver. It can only access containers on the same node, which works perfectly
for the Compose stack:
```bash
docker network create --driver bridge devcoin_bridge_private
```
As you can see, all services except the `proxy` are connected to the default network.
This is because we want all services to freely communicate with each other in this private
network. It's called private since we don't expose any ports to the outside world.

Now let's create the proxy network, which is used to communicate with the outside world:
```bash
docker network create --driver bridge devcoin_bridge_proxy
``` 

Isolated network:

![Private network](/images/private_network.png)

Exposed network (proxy):

![Exposed network](/images/exposed_network.png)

Inspect the private network:
```bash
docker network inspect devcoin_bridge_private
```

Inspect the proxy network:
```bash
docker network inspect devcoin_bridge_proxy
```

So, now let's scale this application:
```bash
docker-compose scale miner=3
```

Yes, we scaled and now we can mine our desired DevCoin three times faster.
But what if we want more? Docker Compose only utilizes the power of a single 
node, meaning we can only scale horizontally. We could add more CPU and 
memory on the node, but eventually we'll run out of options.

In conclusion: Docker Compose is great for development purposes, but Swarm is
more suitable for a scalable production environment. By the way, make sure
you write integration tests in a cluster. I once made a video-transcoder that
was developed on a single node. It would scan a directory for newly uploaded
content, and started transcoding the files. Worked perfectly in development,
but when we scaled it in a cluster you can already guess what happened:
All running transcoders discovered the same file at the same time. We
developed a solution that wasn't scalable by default and we found out too late.
How we solved it? We added a message queue that would send a message to one of
the transcoders when it finished uploading.

Let's bring our stack down and continue with the next chapter:
```bash
docker-compose down
```

# Enter Swarm
Starting a cluster is very easy. To illustrate this, I'm going to use [Play With Docker](http://www.play-with-docker.com).
To start a cluster, we need to enable Swarm mode on the first node:
```bash
export IP=$(docker-machine ip $(docker-machine active))
docker swarm init --advertise-addr $IP
```

To let other nodes join our cluster, we need to obtain a token. Let's get the token first:
```bash
export TOKEN_MANAGER=$(docker swarm join-token -q manager)
export TOKEN_WORKER=$(docker swarm join-token -q worker)
```

Now let's add two extra managers to the cluster:
```bash
for i in 2 3; do
    eval $(docker-machine env swarm-$i)
    docker swarm join --token $TOKEN_MANAGER $IP:2377;
done;
```

Finally, let's add two workers:
```bash
for i in 4 5; do
    eval $(docker-machine env swarm-$i)
    docker swarm join --token $TOKEN_WORKER $IP:2377;
done;
```

Let's check how our cluster looks by inspecting the nodes:
```bash
eval $(docker-machine env swarm-1)
docker node ls
```

# How does Docker Swarm work?    
* Swarm keeps internal state of the cluster through **raft consensus**
* This means all nodes maintain information about the current state of the cluster.
* As long as the quorum (N/2)+1 is maintained, any other manager can take over tasks from a failing node.
* That's why we always have a an odd number of managers.
    * 3 managers, 1 failing, CLUSTER OK
    * 4 managers, 2 failing, CLUSTER NOT OK
    * 5 managers, 2 failing, CLUSTER OK

![Exposed network](/images/raft_consensus.jpg)

Let's put that to practise.

# Docker Swarm Services
To show you how **raft consensus** works we'll need to start a service. 
A service describes a task for the Swarm cluster:

Examples:
* Which image to run
* What command to run for the image
* How many replicas

Let's start a simple service and let it sleep for a long time:
```bash
docker service create --name sleeper alpine:latest sleep 100000
```

As you can see a single replica has been started on one of the nodes.
Let's kill it and see what happens.

Swarm noted that a task failed, according to the service definition
there should always be a running `sleeper` task, so Swarm created an
additional task to replace the broken task.

Now let's scale our service a bit:
```bash
docker service update --replicas 5 sleeper
docker service ps sleeper
```

And simulate a node failure on a worker:
```bash
docker node update --availability drain node5
docker service ps sleeper
```
As you can see, the tasks of the broken node were moved to the healthy nodes.
Where we used to have service discovery with a tool like Consul, this is now supported
out of the box in Docker Swarm.

Let's fail some more nodes and eventually lose the quorum. The moment we lose the quorum
our cluster doesn't work anymore. We should prevent this from happening
at all times.

## Local machine
```bash
for i in 1 2 3 4 5; do docker-machine create --driver virtualbox swarm-$i; done;
```

## AWS
Set the necessary environment variables. I stored the access keys in a file to prevent
anyone from seeing the output. Docker Secrets work in a similar way, but we'll get to that.
```bash
export AWS_ACCESS_KEY_ID=$(cat ~/www/keys/aws_access_id)
export AWS_SECRET_ACCESS_KEY=$(cat ~/www/keys/aws_access_secret)
export AWS_DEFAULT_REGION=eu-west-1
```

Let's see where we can deploy out cluster.
```bash
aws ec2 describe-availability-zones --region $AWS_DEFAULT_REGION
```

Create the manager first:
```bash
docker-machine create \
    --driver=amazonec2 --amazonec2-zone a \
    --amazonec2-tags "type,manager" \
    swarm-1
```

This created an AWS instance with Docker installed. It also created some security groups
to allow communication with other AWS instances we'll start later.

Before we can init a cluster we need to know the private IP-address of the instance.
Unfortunately, `docker-machine ip swarm-1` returns the public IP-address of the instance.
That's not what we want, so we'll grab the private IP-address using AWS console and JQ:
```bash
export MANAGER_IP=$(aws ec2 describe-instances \
    --filter Name=tag:Name,Values=swarm-1 Name=instance-state-name,Values=running | \
    jq -r ".Reservations[0].Instances[0].PrivateIpAddress")
```

Now we can init the cluster:
```bash
eval $(docker-machine env swarm-1)
docker swarm init --advertise-addr $MANAGER_IP
export MANAGER_TOKEN=$(docker swarm join-token -q manager)
```

Adding security group to allow HTTP-traffic which I created upfront:
```bash
INSTANCE_ID=$(aws ec2 describe-instances \
    --filter Name=tag:Name,Values=swarm-1 Name=instance-state-name,Values=running | \
    jq -r ".Reservations[0].Instances[0].InstanceId")
aws ec2 modify-instance-attribute --instance-id $INSTANCE_ID --groups sg-aab9b3d1 sg-839266f8
```

Let's also assign it an static IP so we can resolve it later:
```bash
aws ec2 associate-address --instance-id $INSTANCE_ID --allocation-id eipalloc-e436e5d9
docker-machine regenerate-certs swarm-1
```

Now let's create the cluster:
```bash
for i in 2 3; do
    docker-machine create \
        --driver=amazonec2 --amazonec2-zone b \
        --amazonec2-tags "type,manager" \
        swarm-$i
    eval $(docker-machine env swarm-$i)
    IP=$(aws ec2 describe-instances \
        --filter Name=tag:Name,Values=swarm-$i Name=instance-state-name,Values=running \
        | jq -r ".Reservations[0].Instances[0].PrivateIpAddress")
    docker swarm join \
        --token $MANAGER_TOKEN \
        --advertise-addr $IP \
        $MANAGER_IP:2377
    INSTANCE_ID=$(aws ec2 describe-instances \
        --filter Name=tag:Name,Values=swarm-$i Name=instance-state-name,Values=running \
        | jq -r ".Reservations[0].Instances[0].InstanceId")
    aws ec2 modify-instance-attribute --instance-id $INSTANCE_ID --groups sg-aab9b3d1 sg-839266f8
done
```

# Docker registry
For the next part of the workshop we'll be working with a real cluster.
Docker Registry has three possibilities of distributing images:
* Docker Hub
* Docker Registry
* Docker Trusted Registry

We'll cover the open source options shortly. Main benefits are:
* Tightly control where your images are being stored
* Fully own your images distribution pipeline
* Integrate image storage and distribution tightly into your in-house development workflow

To create our local registry within our cluster we'll start it as a service:
```bash
docker service create --name registry --publish 5000:5000 registry
```

We can confirm that the service is now running:
```bash
docker service ls
```

Let's build the images we want to push to our registry:
```bash
docker-compose -f docker-compose.swarm.yml build api miner
```

And finally push them into our registry:
```bash
docker-compose -f docker-compose.swarm.yml push api miner
```

The API and Miner image will now be pulled from our own registry,
the others will be pulled from the Docker Hub.

# Docker Stack
With Docker Stack we can deploy all our services at once like we're
used to in Docker Compose:
```bash
docker stack deploy -c docker-compose.swarm.yml devcoin
```

Before we can do this we need to create the specific Swarm *overlay* networks:
```bash
docker network create --driver overlay devcoin_overlay_private
docker network create --driver overlay devcoin_overlay_proxy
```

These networks work in a different way:
![Overlay network](/images/overlay_network.png)

Now we can start the stack:
```bash
docker stack deploy -c docker-compose.swarm.yml devcoin
```

An overlay network can access and resolve containers across nodes!
```bash
docker network inspect devcoin_overlay_private 
```

Swarm performs load balancing itself and assigns each container with a private IP-address.
They are resolvable by the network alias. For example, the miner service will have the
`miner` alias:
```bash
docker service inspect devcoin_miner
```

# Configs
It doesn't make sense to store config values in source control since they might vary per install.
In the past we added the config to an extended base image to achieve this.
Mind that we cannot use volumes here, since the config file might not be present on the host. 
To solve this, we can manage our configurations within Docker Swarm.

One of the examples is our Miner. We define it's mining speed by a config file:
```bash
docker config create miner ./miner/config.php
```

Same goes for our reverse proxy:
```bash
docker config create traefik ./proxy/config/traefik-swarm.toml
```

Now let's deploy our stack with the new config:
```bash
docker stack deploy -c docker-compose.final.yml devcoin
```

Rotating the config:
```bash
docker config create miner_v2 ./miner/config.php
docker service update --config-rm miner --config-add source=miner_v2,target=/var/www/html/config/config.php devcoin_miner
```

# Secrets
A lot of times secrets are added as environment variables. Docker utilizes secrets by mounting them at runtime.

Let's create a secret:
```bash
echo "this-is-a-password" | docker secret create my-password -
```

Now let's create a service that uses that password:
```bash
docker service create --name sleeper --secret my-password alpine:latest sleep 100000
```

Enter the container and show the password.

Put Redis behind a password we don't know!
```bash
openssl rand -base64 20 | docker secret create redis -
```

Now:
* Add the secret as an external secret in the compose file
* Mount the secret in the Redis service
* Mount the secret in the Miner service (it uses Redis)
* Change the default command to: `["sh", "-c", "redis-server --requirepass \"$$(cat /run/secrets/redis)\""]`
* Redeploy the stack

https://matthiasnoback.nl/2017/06/making-a-docker-image-ready-for-swarm-secrets/

# TODO
* Set DNS records to allow live deployment

# What we didn't cover
* Rolling updates
* Self-healing clusters with rollback
* Logging
* Monitoring

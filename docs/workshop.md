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

# Introduction: Docker Compose versus Docker Swarm

* docker-compose works only on a **single** node.
* Swarm is suitable for clustering, thus working on **multiple** nodes

```bash
docker-compose up -d
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
docker swarm init --advertise-addr $(docker-machine ip $(docker-machine active))
```

To let other nodes join our cluster, we need to obtain a token. Let's get the token first:
```bash
docker swarm join-token manager
docker swarm join-token worker
```

Now copy the result into the docker engine of the other nodes. 
We'll create two additional managers and two workers.

Let's check how our cluster looks by inspecting the nodes:
```bash
docker node ls
```

# How does Docker Swarm work?    
* Swarm keeps internal state of the cluster through **raft consensus**
* This means all nodes maintain information about the current state of the cluster.
* As long as the quorum is maintained, any other manager can take over tasks from a failing node.

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

# Docker registry
For the next part of the workshop we'll be working with a real cluster.
```bash
for i in 1 2 3; do docker-machine create --driver virtualbox swarm-$i; done;
```

* Why you need a registry

# Docker Stack
Deploy a stack using a compose file

# Setting up a reverse proxy
* Use Configs
* Explain we don't need to expose ports to the world.
* Single point of entry to our applications

# Secrets
Put Redis behind a password we don't know!
https://matthiasnoback.nl/2017/06/making-a-docker-image-ready-for-swarm-secrets/

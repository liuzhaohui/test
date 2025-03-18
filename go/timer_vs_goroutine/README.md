# Compare AfterFunc and long run goroutine
## AfterFunc
* server `./compare -num 100 -mode timer`
* load test `wrk -t10 -c100 -d60s http://localhost:8080/echo`

```
Running 1m test @ http://localhost:8080/echo
  10 threads and 100 connections
  Thread Stats   Avg      Stdev     Max   +/- Stdev
    Latency     1.29ms  439.52us  31.67ms   87.56%
    Req/Sec     7.72k     1.12k   80.05k    92.90%
  4612454 requests in 1.00m, 629.03MB read
Requests/sec:  76739.37
Transfer/sec:     10.47MB
```

## Long Run Goroutine
* server `./compare -num 100 -mode goroutine`
* load test `wrk -t10 -c100 -d60s http://localhost:8080/echo`

```
Running 1m test @ http://localhost:8080/echo
  10 threads and 100 connections
  Thread Stats   Avg      Stdev     Max   +/- Stdev
    Latency     1.37ms    1.23ms  63.08ms   99.07%
    Req/Sec     7.58k     0.85k    9.20k    76.58%
  4527467 requests in 1.00m, 617.44MB read
Requests/sec:  75422.21
Transfer/sec:     10.29MB
```

## Compared
Contrained to 4 cpus and ran with 100 AfterFuncs or long run goroutines. Load test using `wrk`, with 10 threads and 100 connectioins, lasted for 1 minute.
Both `AfterFunc` and `Long Rujn Goroutine` process aproximately the same amount of requests( AfterFunc processes a little bit more). But AfterFunc has better latencies(low average latency and standard deviation)
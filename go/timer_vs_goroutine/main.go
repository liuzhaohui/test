package main

import (
	"context"
	"encoding/json"
	"flag"
	"fmt"
	"log"
	"math/rand"
	"net/http"
	"os"
	"os/signal"
	"runtime"
	"sync/atomic"
	"time"
)

type EchoResponse struct {
	Code int    `json:"code"`
	Msg  string `json:"msg"`
}

type Pipe struct {
	metrics  []uint64
	n        int
	interval time.Duration
	ch       chan any
}

func NewPipe(n int, d time.Duration) *Pipe {
	return &Pipe{metrics: make([]uint64, n), n: n, interval: d, ch: make(chan any)}
}

func (p *Pipe) Stop() {
	close(p.ch)
}

func (p *Pipe) Add() {
	i := rand.Int31n(int32(p.n))
	atomic.AddUint64(&(p.metrics[i]), 1)
}

func (p *Pipe) ModeTimer() {
	timer := make([]*time.Timer, p.n)
	for i := range p.n {
		i := i
		timer[i] = time.AfterFunc(p.interval, func() {
			log.Println("timer", i, "triggered", "current goroutines", runtime.NumGoroutine(),
				"served requests:", atomic.LoadUint64(&(p.metrics[i])))
			select {
			case <-p.ch:
				return
			default:
			}
			timer[i].Reset(p.interval)
		})
	}
}

func (p *Pipe) ModeGoroutine() {
	for i := range p.n {
		i := i
		go func() {
			tick := time.NewTicker(p.interval)
			for {
				select {
				case <-tick.C:
					log.Println("goroutine", i, "triggered", "current goroutines", runtime.NumGoroutine(),
						"served requests:", atomic.LoadUint64(&(p.metrics[i])))
				case <-p.ch:
					return
				}
			}
		}()
	}
}

func main() {
	runtime.GOMAXPROCS(4) // Restrict Go to 4 CPU cores
	fmt.Println("Using", runtime.GOMAXPROCS(0), "cores")
	port := flag.Int("port", 8080, "port to listen")
	mode := flag.String("mode", "timer", "timer or goroutine")
	num := flag.Int("num", 1, "num of timers or goroutines")
	interval := flag.Duration("interval", time.Second, "timers or goroutines trigger work interval")
	flag.Parse()
	fmt.Println("port", *port)
	fmt.Println("mode", *mode)
	fmt.Println("num", *num)
	fmt.Println("interval", *interval)
	// initialize mode
	pipe := NewPipe(*num, *interval)
	switch *mode {
	case "timer":
		pipe.ModeTimer()
	case "goroutine":
		pipe.ModeGoroutine()
	default:
		fmt.Println("mode", *mode, "unsupported")
		return
	}
	// start server
	server := &http.Server{
		Addr:    fmt.Sprintf(":%d", *port),
		Handler: http.DefaultServeMux,
	}
	// Setup echo server handler
	http.HandleFunc("/echo", func(w http.ResponseWriter, r *http.Request) {
		// log.Println("serve one")
		pipe.Add()
		msg, _ := json.Marshal(EchoResponse{
			Code: 0,
			Msg:  "Success",
		})
		w.Write([]byte(msg))
	})

	if err := server.ListenAndServe(); err != nil && err != http.ErrServerClosed {
		log.Fatalf("ListenAndServe error: %v", err)
	}

	// Set up channel to listen for interrupt (Ctrl+C) or terminate signals
	stop := make(chan os.Signal, 1)
	signal.Notify(stop, os.Interrupt)

	// Block until a signal is received
	<-stop
	log.Println("Shutting down server...")
	pipe.Stop()

	// Create a context with timeout for graceful shutdown
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	// Attempt graceful shutdown
	if err := server.Shutdown(ctx); err != nil {
		log.Fatalf("Server forced to shutdown: %v", err)
	}
}

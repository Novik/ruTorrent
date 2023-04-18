/**
 * Use a BackgroundTask for a computationally expensive task.
 * The task should be split into step functions which take an equal duration of less than 1ms.
 * This class uses `requestIdleCallback`, see: https://developer.mozilla.org/en-US/docs/Web/API/Background_Tasks_API
 * Thus, for DOM updates `requestAnimationFrame` should be used.
 *
 * ```javascript
 * const task = new BackgroundTask();
 * const numbers = [];
 * const update = (num) => { numbers.push(num); };
 *
 * await task
 *  .enqueue([update, 0], [update, 1])
 *  .enqueueFunc(update, 2)
 *  .enqueueFunc(update, 3)
 *  .run();
 *
 * const [x,y,z,w] = await task.map(numbers, (x, index) => x ** 2).run();
 * console.assert(x === 0 && y === 1 && z === 4 && w === 9);
 * ```
 */
export class BackgroundTask {
  #promise;
  #promiseFuncs;
  #taskHandle;
  #taskSteps;
  #taskOutputs;
  #hurry;
  constructor() {
    this.#taskDone();
  }

  #runTaskQueue(deadline) {
    // Run #taskSteps until deadline is reached
    while (
      (deadline.didTimeout || deadline.timeRemaining() >= 1) &&
      this.#taskSteps.length
    ) {
      const [taskFunc, taskInput] = this.#taskSteps.shift();
      const index = this.#taskOutputs.length;
      try {
        const output = taskFunc(taskInput, index);
        this.#taskOutputs.push(output);
      } catch (error) {
        setTimeout(() => this.#taskDone(error));
        return;
      }
    }
    this.#scheduleRun();
  }

  #scheduleRun() {
    if (this.#taskSteps.length) {
      if (this.#hurry) {
        this.#runTaskQueue({ didTimeout: true, timeRemaining: () => 0 });
      } else {
        this.#taskHandle = window.requestIdleCallback(
          (deadline) => {
            this.#taskHandle = 0;
            this.#runTaskQueue(deadline);
          },
          { timeout: 300 }
        );
      }
    } else {
      setTimeout(() => this.#taskDone());
    }
  }

  #taskDone(error) {
    if (this.#promise) {
      this.#promise = null;
      const [resolve, reject] = this.#promiseFuncs;
      this.#promiseFuncs = null;
      if (this.#taskHandle) {
        window.cancelIdleCallback(this.#taskHandle);
        this.#taskHandle = 0;
      }
      if (error) reject(error);
      else resolve(this.#taskOutputs);
    }
    this.#taskSteps = [];
    this.#taskOutputs = [];
  }

  #run() {
    this.#promise = new Promise((resolve, reject) => {
      this.#promiseFuncs = [resolve, reject];
      this.#scheduleRun();
    });
    return this.#promise;
  }

  enqueue(...taskSteps) {
    this.#taskSteps = this.#taskSteps.concat(taskSteps);
    return this;
  }

  enqueueFunc(taskFunc, data) {
    return this.enqueue([taskFunc, data]);
  }

  run(hurry) {
    this.#hurry = hurry;
    return this.#promise ?? this.#run();
  }

  map(taskInputs, mapFunc) {
    return this.enqueue(...taskInputs.map((val) => [mapFunc, val]));
  }

  reset(reason = "reset") {
    this.#taskDone(reason);
    return this;
  }
}
